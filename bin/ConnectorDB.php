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

use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\Core\System\BeanstalkClient;
use Modules\ModuleExtendedCDRs\Lib\CacheManager;
use Modules\ModuleExtendedCDRs\Lib\HistoryParser;
use Modules\ModuleExtendedCDRs\Lib\Logger;
use Exception;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use Modules\ModuleExtendedCDRs\Models\CallHistory;
use Modules\ModuleExtendedCDRs\Models\ModuleExtendedCDRs;
use Phalcon\Db\Enum;
use DateTime;
use getID3;
use getid3_writetags;

require_once 'Globals.php';
require_once(dirname(__DIR__).'/vendor/autoload.php');

class ConnectorDB extends WorkerBase
{
    private Logger $logger;

    public int $cdrOffset = 1;
    public string $referenceDate = '';
    public bool $disableIvr = true;

    private int $lastSyncTime = 0;

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
        while ($this->needRestart === false) {
            $beanstalk->wait(10);
            $this->logger->rotate();
            $this->syncCdrData();
        }
    }


    /**
     * Получение настроек модуля.
     * @param int $newCdrOffset
     * @return void
     */
    public function updateSettings(int $newCdrOffset=0):void
    {
        $settings  = ModuleExtendedCDRs::findFirst();
        if(!$settings){
            $settings = new ModuleExtendedCDRs();
        }
        if($newCdrOffset > 0){
            $minOffset = HistoryParser::getMinCdrId();
            $settings->cdrOffset = max($newCdrOffset,$minOffset);
            $settings->save();
        }
        if(empty($settings->referenceDate) || (empty($settings->cdrOffset) && $settings->referenceDate !== '0') ){
            $settings->cdrOffset = 1;
            $settings->referenceDate = date("Y-m-d H:i:s.0", strtotime("-1 days"));
            $settings->save();
        }
        $this->cdrOffset     = (int)$settings->cdrOffset;
        $this->referenceDate = $settings->referenceDate;
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
            }else{
                $this->logger->writeError($data);
            }
            if(isset($data['need-ret'])){
                $res_data = $this->saveResultInTmpFile($res_data);
                $tube->reply($res_data);
            }
        }
    }

    /**
     * Сериализует данные и сохраняет их во временный файл.
     * @param $data
     * @return string
     */
    private function saveResultInTmpFile($data):string
    {
        try {
            $res_data = json_encode($data, JSON_THROW_ON_ERROR);
        }catch (\JsonException $e){
            return '';
        }
        $dirsConfig = $this->di->getShared('config');
        $tmoDirName = $dirsConfig->path('core.tempDir') . '/ModuleExtendedCDRs';
        Util::mwMkdir($tmoDirName, true);
        if (file_exists($tmoDirName)) {
            $tmpDir = $tmoDirName;
        }else{
            $tmpDir = '/tmp/';
        }
        $downloadCacheDir = $dirsConfig->path('www.downloadCacheDir');
        if (!file_exists($downloadCacheDir)) {
            $downloadCacheDir = '';
        }
        $fileBaseName = md5(microtime(true));
        // "temp-" in the filename is necessary for the file to be automatically deleted after 5 minutes.
        $filename = $tmpDir . '/temp-' . $fileBaseName;
        file_put_contents($filename, $res_data);
        if (!empty($downloadCacheDir)) {
            $linkName = $downloadCacheDir . '/' . $fileBaseName;
            // For automatic file deletion.
            // A file with such a symlink will be deleted after 5 minutes by cron.
            Util::createUpdateSymlink($filename, $linkName, true);
        }
        chown($filename, 'www');
        return $filename;
    }

    /**
     * Метод следует вызывать при работе с API из прочих процессов.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return array
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true):array
    {
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        $object = [];
        try {
            if($retVal){
                $req['need-ret'] = true;
                $result = $client->request(json_encode($req, JSON_THROW_ON_ERROR), 60);
            }else{
                $client->publish(json_encode($req, JSON_THROW_ON_ERROR));
                return [];
            }
            if(file_exists($result)){
                $object = json_decode(file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);
                unlink($result);
            }
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
        return substr($number, -9);
    }

    /**
     * Запускаем парсер истории звонков. Парсер сохраняет кэш, кто последний говорил с клиентом.
     * @return void
     */
    public function syncCdrData():void
    {
        if(time() - $this->lastSyncTime < 10){
            return;
        }
        $this->lastSyncTime = time();
        $oldOffset = $this->cdrOffset;
        $this->logger->writeInfo('Start offset...'. $oldOffset);

        $cdrData = HistoryParser::getHistoryData($this->cdrOffset);
        $this->logger->writeInfo("New offset $this->cdrOffset, ".'count ...'. count($cdrData));

        $arrKeys = (new CallHistory())->toArray();
        unset($arrKeys['id']);
        foreach ($cdrData as $cdr){
            foreach ($cdr['rows'] as $row){
                /** @var CallHistory $dbData */
                $dbData = CallHistory::findFirst("UNIQUEID='{$row['UNIQUEID']}'");
                if(!$dbData){
                    $dbData = new CallHistory();
                }
                foreach ($row as $key => $value){
                    if(!array_key_exists($key, $arrKeys)){
                        continue;
                    }
                    $dbData->$key = $value;
                }
                foreach ($cdr as $key => $value){
                    if(!array_key_exists($key, $arrKeys)){
                        continue;
                    }
                    $dbData->$key = $value;
                }
                $this->updateMp3Tags($dbData);
                $this->setCallType($dbData);
                $dbData->save();
                unset($dbData);
            }
        }
        if($oldOffset !== $this->cdrOffset){
            $this->logger->writeInfo(" $oldOffset !== $this->cdrOffset ");
            $lastCdrData = HistoryParser::getLastCdrData();
            if(!empty($lastCdrData)){
                $tmpCdrData = [
                    'lastId'    => 1*$lastCdrData['id'],
                    'lastDate'  => $lastCdrData['start'],
                    'nowId'     => $this->cdrOffset
                ];
                CacheManager::setCacheData(HistoryParser::CDR_SYNC_PROGRESS_KEY, $tmpCdrData);
            }
            $this->updateSettings($this->cdrOffset);
        }
    }

    /**
     * Устанавливает тег title для mp3 файла
     * @param CallHistory $data
     * @return void
     */
    private function updateMp3Tags(CallHistory $data):void
    {
        if(!file_exists($data->recordingfile)){
            return;
        }

        $cover_image = dirname(__DIR__).'/public/assets/img/mikopbx-picture.jpg';
        $cover_image_custom = dirname(__DIR__,2).'/ModuleExtendedCDRs-logo-mp3.jpg';
        if(file_exists($cover_image_custom)){
            $cover_image = $cover_image_custom;
        }

        $getID3    = new getID3();
        $tagWriter = new getid3_writetags();

        $tagWriter->filename          = $data->recordingfile;
        $tagWriter->tagformats        = ['id3v2.3'];
        $tagWriter->overwrite_tags    = true;
        $tagWriter->tag_encoding      = 'UTF-8';
        $tagWriter->remove_other_tags = false;

        $formattedDate  = date('Y-m-d-H_i', strtotime($data->start));
        $uid            = str_replace('mikopbx-', '', $data->linkedid);
        $prettyFilename = "$uid-$formattedDate-$data->src_num-$data->dst_num";

        $soxPath = Util::which('soxi');
        $tagWriter->tag_data = [
            'title'   => [$prettyFilename],
            'attached_picture' => [
                [
                    'data' => file_get_contents($cover_image),
                    'picturetypeid' => 0x03,
                    'description' => 'MikoPBX',
                    'mime' => 'image/jpeg'
                ]
            ],
            'comment' => [md5($prettyFilename.'_'.trim(shell_exec("$soxPath $data->recordingfile")??''))            ]
        ];
        $tagWriter->WriteTags();
        unset($getID3, $tagWriter);

        $dirLink = str_replace('/monitor/', '/pretty-monitor/', dirname($data->recordingfile,2));
        $mkdirPath = Util::which('mkdir');
        $lnPath = Util::which('ln');
        shell_exec("$mkdirPath -p $dirLink; $lnPath -s $data->recordingfile $dirLink/$prettyFilename.mp3");

    }

    /**
     * @param $dbData
     * @return void
     */
    private function setCallType($dbData):void
    {
        $number = '';
        if($dbData->typeCall === CallHistory::CALL_TYPE_OUTGOING){
            if($dbData->billsec === '0'){
                $dbData->stateCall = CallHistory::CALL_STATE_OUTGOING_FAIL;
            }else{
                // Успешный исходящий.
                $dbData->stateCall = CallHistory::CALL_STATE_OK;
                $number = $dbData->dst_num;
            }
        }elseif($dbData->typeCall === CallHistory::CALL_TYPE_INCOMING && $dbData->is_app === '1'){
            $dbData->stateCall = CallHistory::CALL_STATE_APPLICATION;
        }elseif($dbData->typeCall === CallHistory::CALL_TYPE_MISSED){
            $dbData->stateCall = CallHistory::CALL_STATE_MISSED;
        }elseif ($dbData->typeCall === CallHistory::CALL_TYPE_INCOMING){
            $dbData->stateCall = CallHistory::CALL_STATE_OK;
            $number = $dbData->src_num;
        }elseif($dbData->billsec === '0'){
            // Внутренний.
            $dbData->stateCall = CallHistory::CALL_STATE_OUTGOING_FAIL;
        }else{
            $dbData->stateCall = CallHistory::CALL_STATE_OK;
        }

        if($dbData->stateCall === CallHistory::CALL_STATE_OK && !empty($number)){
            try {
                $dateTime = new DateTime($dbData->start);
            }catch (Exception $e){
                return;
            }
            $dateTime->modify('-60 minutes');
            $oldStart = $dateTime->format('Y-m-d H:i:s');
            // Ищем последний вызов по этому номеру телефона.
            $filter = [
                '(dstIndex = :number: OR srcIndex = :number:) AND start BETWEEN :dateFromPhrase1: AND :dateFromPhrase2: AND linkedid<>:linkedid:',
                'columns' => 'typeCall',
                'bind' => [
                    'linkedid' => $dbData->linkedid,
                    'number' => self::getPhoneIndex($number),
                    'dateFromPhrase1' => $oldStart,
                    'dateFromPhrase2' => $dbData->start,
                ],
                'order' => ['start desc'],
                'limit' => 1
            ];
            $oldHistory = CallHistory::find($filter);
            foreach ($oldHistory as $oldCdr){
                if($oldCdr->typeCall === CallHistory::CALL_TYPE_MISSED
                    && $dbData->typeCall === CallHistory::CALL_TYPE_INCOMING){
                    $dbData->stateCall = CallHistory::CALL_STATE_RECALL_CLIENT;
                }elseif($oldCdr->typeCall === CallHistory::CALL_TYPE_MISSED
                    && $dbData->typeCall === CallHistory::CALL_TYPE_OUTGOING){
                    $dbData->stateCall = CallHistory::CALL_STATE_RECALL_USER;
                }
            }

            $filter = [
                'billsec>0 AND start < :dateFromPhrase1: AND linkedid=:linkedid:',
                'columns' => 'typeCall',
                'bind' => [
                    'linkedid'        => $dbData->linkedid,
                    'dateFromPhrase1' => $dbData->start,
                ],
                'order' => ['start desc'],
                'limit' => 1
            ];
            $oldHistory = CallHistory::find($filter);
            if(!empty($oldHistory->toArray())){
                $dbData->stateCall = CallHistory::CALL_STATE_TRANSFER;
            }
        }
    }

    public function getCdr(array $filter = []): array
    {
        $res_data = [];
        if ($this->filterNotValid($filter)) {
            return $res_data;
        }
        try {
            $res = CallHistory::find($filter);
            $res_data = $res->toArray();
        } catch (\Throwable $e) {
            $res_data = [];
        }
        return $res_data;
    }

    /**
     * Возвращает количество записпей за период с отбором по номерам.
     * @param string $start
     * @param string $end
     * @param array  $numbers
     * @param array  $additionalNumbers
     * @return array
     */
    public function getCountCdr(string $start, string $end, array $numbers, array $additionalNumbers): array
    {
        $bindParams = [
            ':start' => $start,
            ':end'   => $end
        ];
        $condition = "cdr_general.start BETWEEN :start AND :end";
        if (!empty($numbers)) {
            foreach ($numbers as $value) {
                $bindParams[":Index$value"] = $value;
            }
            $placeholders = implode(
                ', ',
                array_map(static function ($value){
                    return ":Index$value";
                }, $numbers)
            );
            $condition .= " AND (cdr_general.dstIndex IN ($placeholders) OR cdr_general.srcIndex IN ($placeholders))";
        }
        if (!empty($additionalNumbers)) {
            foreach ($additionalNumbers as $value) {
                $bindParams[":IndexAdd$value"] = $value;
            }
            $placeholders = implode(
                ', ',
                array_map(static function ($value){
                    return ":IndexAdd$value";
                }, $additionalNumbers)
            );
            $condition .= " AND (cdr_general.dstIndex IN ($placeholders) OR cdr_general.srcIndex IN ($placeholders))";
        }

        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);
        $sql = "
            SELECT 
                COALESCE(SUM(IIF(t.typeCall=0,1,0)),0) AS cINNER, 
                COALESCE(SUM(IIF(t.typeCall=1,1,0)),0) AS cOUTGOING, 
                COALESCE(SUM(IIF(t.typeCall=2,1,0)),0) AS cINCOMING, 
                COALESCE(SUM(IIF(t.typeCall=3,1,0)),0) AS cMISSED, 
                COUNT(t.linkedid) AS cCalls 
            FROM (
                SELECT 
                    MIN(cdr_general.id) AS id, 
                    MAX(cdr_general.typeCall) AS typeCall, 
                    cdr_general.linkedid AS linkedid 
                FROM cdr_general 
                WHERE {$condition} 
                GROUP BY cdr_general.linkedid
            ) AS t
        ";
        $result = $db->query($sql, $bindParams);
        $result->setFetchMode(Enum::FETCH_ASSOC);
        $row = $result->fetch();
        return is_array($row)?$row:[];
    }


    /**
     * Check if the filter has any invalid bind parameters.
     *
     * @param array $filter The filter to validate.
     * @return bool True if the filter has invalid bind parameters, false otherwise.
     */
    private function filterNotValid(array $filter): bool
    {
        $haveErrors = false;
        $validValue = ['0', ''];
        if (isset($filter['bind'])) {
            if (is_array($filter['bind'])) {
                foreach ($filter['bind'] as $bindValue) {
                    if (empty($bindValue) && !in_array($bindValue, $validValue, true)) {
                        $haveErrors = true;
                    }
                }
            } else {
                $haveErrors = true;
            }
        }
        return $haveErrors;
    }

}

if(isset($argv) && count($argv) !== 1
    && Util::getFilePathByClassName(ConnectorDB::class) === $argv[0]){

    ini_set('memory_limit', '512M');
    ConnectorDB::startWorker($argv??[]);
}
