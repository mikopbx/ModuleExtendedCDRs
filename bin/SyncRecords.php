<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleExtendedCDRs\bin;

use GuzzleHttp\Exception\GuzzleException;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleExtendedCDRs\Lib\HistoryParser;
use Modules\ModuleExtendedCDRs\Lib\Logger;
use Exception;
use Modules\ModuleExtendedCDRs\Models\ExportResults;
use Modules\ModuleExtendedCDRs\Models\ExportRules;
use Modules\ModuleExtendedCDRs\Models\ModuleExtendedCDRs;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Formatter\Line as LineFormatter;

require_once 'Globals.php';

class SyncRecords extends WorkerBase
{
    private Logger $logger;

    public int $cdrOffset = 1;
    public string $referenceDate = '';
    public bool $disableIvr = true;

    private array $rulesData = [];
    private array $rulesHeaders = [];

    /**
     * Старт работы листнера.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger   = new Logger('ConnectorDB', 'ModuleExtendedCDRs');
        $this->logger->writeInfo('Starting...');

        $this->updateSettings();
        $beanstalk      = new BeanstalkClient(self::class);
        $beanstalk->subscribe(self::class, [$this, 'onEvents']);
        $beanstalk->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        while (true) {
            $beanstalk->wait();
            $this->logger->rotate();
        }
    }

    /**
     * Получение настроек модуля.
     * @param int $newCdrOffset
     * @return void
     */
    public function updateSettings(int $newCdrOffset=0):void
    {
        $settings = ModuleExtendedCDRs::findFirst();
        if(!$settings){
            $settings = new ModuleExtendedCDRs();
        }
        if($newCdrOffset > 0){
            $settings->cdrOffset = $newCdrOffset;
            $settings->save();
        }
        if(empty($settings->cdrOffset) || empty($settings->referenceDate)){
            $settings->cdrOffset = 1;
            $settings->referenceDate = date("Y-m-d H:i:s.0", strtotime("-1 days"));
            $settings->save();
        }
        $this->cdrOffset     = (int)$settings->cdrOffset;
        $this->referenceDate = $settings->referenceDate;

        $this->rulesData = [];
        $this->rulesHeaders = [];
        /** @var ExportRules $rule */
        $rules = ExportRules::find();
        foreach ($rules as $rule){
            if(!filter_var($rule->dstUrl, FILTER_VALIDATE_URL)){
                continue;
            }
            $usersId = explode(',', $rule->users);
            $extensions = Extensions::find([
                'conditions' => 'type = :type: AND userid IN ({userid:array})',
                'bind'       => [
                    'type' => Extensions::TYPE_SIP,
                    'userid'  => $usersId,
                ],
            ]);
            foreach ($extensions as $extension){
                $this->rulesData[$rule->dstUrl][] = $extension->number;
            }
            $this->rulesHeaders[$rule->dstUrl] = $this->stringToHeadersArray($rule->headers);
        }
    }

    private function stringToHeadersArray(string $headersString):array
    {
        $headerLines = explode("\n", $headersString);
        $httpHeaders = [];
        foreach ($headerLines as $line) {
            [$key, $value] = explode(':', $line, 2);
            $httpHeaders[trim($key)] = trim($value);
        }
        return $httpHeaders;
    }

    /**
     * Ответ на запрос состояния сервиса.
     * @param BeanstalkClient $message
     * @return void
     */
    public function pingCallBack(BeanstalkClient $message): void
    {
        parent::pingCallBack($message);
        $this->updateSettings();
        $this->syncCdrData();
        $this->syncFailCdrData();
    }

    /**
     * Получение запросов на идентификацию номера телефона.
     * @param $tube
     * @return void
     */
    public function onEvents($tube): void
    {
        try {
            $data = json_decode($tube->getBody(), true, 512, JSON_THROW_ON_ERROR);
        }catch (Exception $e){
            return;
        }
        if($data['action'] === 'invoke'){
            $res_data = [];
            $funcName = $data['function']??'';
            if(method_exists($this, $funcName)){
                if(count($data['args']) === 0){
                    $res_data = $this->$funcName();
                }else{
                    $res_data = $this->$funcName(...$data['args']??[]);
                }
                $res_data = serialize($res_data);
            }else{
                $this->logger->writeError($data);
            }
            if(isset($data['need-ret'])){
                $tube->reply($res_data);
            }
        }
    }

    /**
     * Выполнение меодов worker, запущенного в другом процессе.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return array|bool|mixed
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true){
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        try {
            if($retVal){
                $req['need-ret'] = true;
                $result = $client->request(json_encode($req, JSON_THROW_ON_ERROR), 20);
            }else{
                $client->publish(json_encode($req, JSON_THROW_ON_ERROR));
                return true;
            }
            $object = unserialize($result, ['allowed_classes' => [PBXApiResult::class]]);
        } catch (\Throwable $e) {
            $object = [];
        }
        return $object;
    }

    /**
     * Возвращает усеченный слева номер телефона.
     *
     * @param $number
     *
     * @return bool|string
     */
    public static function getPhoneIndex($number)
    {
        $number = preg_replace('/\D+/', '', $number);
        return substr($number, -10);
    }

    /**
     * Запускаем парсер истории звонков. Парсер сохраняет кэш, кто последний говорил с клиентом.
     * @return void
     */
    public function syncCdrData():void
    {
        sleep(2);
        $oldOffset = $this->cdrOffset;
        $oldOffset = max(HistoryParser::getMinCdrId(), $oldOffset);
        $this->logger->writeInfo('New offset...'. $oldOffset);

        $cdrData = HistoryParser::getHistoryData($this->cdrOffset);


        foreach ($cdrData as $cdr){
            if(!file_exists($cdr['recordingfile'])){
                continue;
            }
            foreach ($this->rulesData as $baseUrl => $numbers){
                if(in_array($cdr['src'], $numbers, true) || in_array($cdr['dst'], $numbers, true) ){
                    $time = time();
                    $exampleStorageFileName = "{$time}_{$cdr['start']}_{$cdr['billsec']}_{$cdr['typeCall']}_{$cdr['src']}_{$cdr['dst']}.mp3";
                    $url = "{$baseUrl}/{$exampleStorageFileName}";

                    $headers = $this->rulesHeaders[$baseUrl];
                    $headers['Expect'] = '100-continue';
                    $result = $this->sendToHost($url, $headers, $cdr['recordingfile']);

                    $resultDb = ExportResults::findFirst("cdrId='{$cdr['id']}'");
                    if(!$resultDb){
                        $resultDb = new ExportResults();
                    }
                    $resultDb->cdrId = $cdr['id'];
                    $resultDb->srcFilename = $cdr['recordingfile'];
                    $resultDb->resFilename = $exampleStorageFileName;
                    $resultDb->result = (int)$result;
                    $resultDb->save();

                    $this->updateSettings($cdr['id']);
                }
            }
        }
        if($oldOffset !== $this->cdrOffset){
            $this->updateSettings($this->cdrOffset);
        }
    }

    /**
     * Повторная синхронизация неудачных
     * @return void
     */
    public function syncFailCdrData():void
    {
        $cdrData = ExportResults::find([
           'conditions' => 'result=0',
           'limit' => 20,
        ]);
        foreach ($cdrData as $resultDb){
            if(!file_exists($resultDb->srcFilename)){
                continue;
            }
            $result = true;
            $resFilenameData    = explode('_',$resultDb->resFilename);
            $dst = Util::trimExtensionForFile($resFilenameData[5]);
            foreach ($this->rulesData as $baseUrl => $numbers){
                // src и dst содержатся в имени файла в позициях 4 и 5;
                if(in_array($resFilenameData[4], $numbers, true) || in_array($dst, $numbers, true) ) {
                    $resFilenameData[0] = time();
                    $resultDb->resFilename = implode('_', $resFilenameData);
                    $url     = "{$baseUrl}/{$resultDb->resFilename}";
                    $headers = $this->rulesHeaders[$baseUrl];
                    $headers['Expect'] = '100-continue';
                    $res  = $this->sendToHost($url, $headers, $resultDb->srcFilename);
                    $result = min($result, $res);
                    if(!$result){
                        break;
                    }
                }
            }
            $resultDb->result = (int)$result;
            $resultDb->save();
        }
    }

    /**
     * Отправка данных на сервер в PUT запросе.
     * @param $url
     * @param $headers
     * @param $localPathToFile
     * @return bool
     */
    public function sendToHost($url, $headers, $localPathToFile):bool
    {
        $thisLogger = $this->logger;
        $stack = HandlerStack::create();
        $stack->push(function (callable $handler) use ($thisLogger) {
            return static function ($request, $options) use ($handler, $thisLogger) {
                // Логируем отправленный запрос
                $resultString = 'Request:'.PHP_EOL;
                $resultString.= $request->getMethod().' ' .$request->getUri().PHP_EOL;
                foreach ($request->getHeaders() as $key => $value) {
                    $resultString .= $key . ': ' . implode(' ', $value) . "\n";
                }
                $thisLogger->info($resultString);
                // Логируем полученный ответ
                return $handler($request, $options)->then(
                    function ($response) use ($thisLogger) {
                        $resultString = 'Response:'.PHP_EOL;
                        $resultString.= $response->getStatusCode().' '.$response->getReasonPhrase().PHP_EOL;
                        foreach ($response->getHeaders() as $key => $value) {
                            $resultString .= $key . ': ' . implode(' ', $value) . "\n";
                        }
                        $thisLogger->info($resultString);

                        return $response;
                    }
                );
            };
        });
        try {
            $client = new Client(['handler' => $stack]);
            $response = $client->request('PUT', $url, [
                'headers' => $headers,
                'body' => fopen($localPathToFile, 'rb'),
                'http_errors' => false,
            ]);
            $res = $response->getStatusCode() === 201;
        }catch (GuzzleException $e){
            $res = false;
        }
        return $res;
    }
 }

if(isset($argv) && count($argv) !== 1){
    ConnectorDB::startWorker($argv??[]);
}
