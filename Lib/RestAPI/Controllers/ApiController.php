<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;

class ApiController extends ModulesControllerBase
{

    /**
     * Пример передачи параметров (без urlencode):
     * DateFrom=19.03.2025 00:00&DateTo=19.03.2025 23:00&PhoneNumbers[]=203&PhoneNumbers[]=201&ExcludeNumbers[]=74952292333&ExcludeNumbers[]=74952295555
     *
     * Пример локального запроса (без авторизации всегда)
     * curl "http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportHistoryDetail?DateFrom=19.03.2025+00%3A00&DateTo=19.03.2025+23%3A00&PhoneNumbers%5B%5D=203&PhoneNumbers%5B%5D=201&ExcludeNumbers%5B%5D=74952292333&ExcludeNumbers%5B%5D=74952295555"
     * @return void
     */
    public function exportHistoryDetail(){
        $gr = new GetReport();
        $dateFrom       = $this->request->get('DateFrom');
        $dateTo         = $this->request->get('DateTo');
        $phoneNumbers   = $this->request->get('PhoneNumbers')??[];
        $excludeNumbers = $this->request->get('ExcludeNumbers')??[];

        $view = $gr->historyDetail($dateFrom, $dateTo, $phoneNumbers, $excludeNumbers);
        $this->echoResponse($view);
        $this->response->sendRaw();
    }

    /**
     * Скачивание записи разговора.
     * /pbxcore/api/cdr/records MIKO AJAM
     * curl -O 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/records?view=/storage/usbdisk1/mikopbx/astspool/monitor/2025/03/20/18/mikopbx-1742482882.0_cE7dn8.mp3'
     * curl -o test.mp3 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/records?CallRecordID=mikopbx-1742368258.4_a9Gp8D'
     */
    public function recordsAction(): void
    {
        $id       = (string)$this->request->get('CallRecordID');
        $filename = (string)$this->request->get('view');

        if(!file_exists($filename) && !empty($id)){
            [$filename] = ConnectorDB::invoke('getRecordingPathByID', [$id]);
        }

        if(!file_exists($filename) || Processes::mwExec("/usr/bin/soxi '$filename'") !== 0){
            $this->sendError(404);
            return;
        }
        $size = filesize($filename);
        $fp = fopen($filename, 'rb');
        if ($fp) {
            $this->response->setHeader('Content-Description', 'mp3 file');
            $this->response->setHeader('Content-Disposition', 'attachment; filename=' . basename($filename));
            $this->response->setHeader('Content-type', 'audio/mpeg');
            $this->response->setHeader('Content-Transfer-Encoding', 'binary');
            $this->response->setContentLength($size);
            $this->response->sendHeaders();
            fpassthru($fp);
        } else {
            $this->sendError(404);
        }
    }


    /**
     * curl 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?reportNameID=CdrQueue&type=json&search=%7B%22dateRangeSelector%22%3A%2209%2F11%2F2025%20-%2008%2F12%2F2025%22%2C%22minBilSec%22%3A%226%22%2C%22minBilSecComp%22%3A%22%3E%3D%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22outgoing-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * curl 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?reportNameID=CdrQueue&type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F12%2F2025%20-%2002%2F12%2F2025%22%2C%22minBilSec%22%3A%226%22%2C%22minBilSecComp%22%3A%22%3E%3D%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22outgoing-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * {"dateRangeSelector":"01/11/2025 - 02/12/2025","minBilSec":"6","minBilSecComp":">=","globalSearch":"","typeCall":"outgoing-calls","additionalFilter":""}
     * @return void
     */
    public function exportHistoryQueue(): void
    {
        ini_set('memory_limit', '2024M');
        ini_set('pcre.backtrack_limit', '10000000');
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if(!is_string($searchPhrase)){
            $this->response->sendRaw();
            return;
        }
        $gr = new GetReport();
        $aggregatedData = self::aggregateCdrData($gr->historyQueue($searchPhrase, null, null));
        $view = (object)$aggregatedData;
        $view->searchPhrase = $searchPhrase;
        $view->title = urldecode($this->request->get('title')??'');
        if($type === 'json'){
            $this->echoResponse((array)$view);
        }elseif($type === 'pdf'){
            GetReport::exporthistoryQueuePdf($view);
            exit();
        }elseif ($type === 'xlsx'){
            GetReport::exporthistoryQueueXls($view);
            exit();
        }
        $this->response->sendRaw();
    }

    public static function aggregateCdrData(array $records): array
    {
        $groups = [];
        foreach ($records as $record) {
            $queueId = $record['queueId']??'';
            $date = $record['date'];
            $key = $date.$queueId;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'queueId' => $queueId,
                    'queueName' => $record['queueName']??'',
                    'date' => $date,
                    'linkedids' => [],
                    'answered_sum' => 0,
                    'missed_sum' => 0,
                    'answeredQueue_sum' => 0,
                    'waitTime_sum' => 0,
                    'waitTimeQueue_sum' => 0,
                ];
            }
            $group =& $groups[$key];
            if (!in_array($record['linkedid'], $group['linkedids'], true)) {
                $group['linkedids'][] = $record['linkedid'];
            }
            $group['answered_sum'] += intval($record['answered']);
            $group['missed_sum'] += intval($record['answered'])===0?1:0;
            $group['answeredQueue_sum'] += (int)$record['answeredQueue'];
            $group['waitTime_sum'] += (int)$record['waitTime'];
            $group['waitTimeQueue_sum'] += (int)$record['waitTimeQueue'];
            unset($group);
        }

        $result = [];
        foreach ($groups as $tmpGroup) {
            $uniqueCalls = count($tmpGroup['linkedids']);
            $avgMissed        = $uniqueCalls > 0 ? round($tmpGroup['missed_sum'] / $uniqueCalls, 2) *100: 0;
            $avgWaitTime      = $uniqueCalls > 0 ? round($tmpGroup['waitTime_sum'] / $uniqueCalls, 2) : 0;
            $avgWaitTimeQueue = $uniqueCalls > 0 ? round($tmpGroup['waitTimeQueue_sum'] / $uniqueCalls, 2) : 0;
            $result[] = [
                'queueId' => $tmpGroup['queueId'],
                'queueName' => $tmpGroup['queueName'],
                'date' => $tmpGroup['date'],
                'totalCalls' => $uniqueCalls,
                'answered' => $tmpGroup['answered_sum'],
                'missed' => $tmpGroup['missed_sum'],
                'answeredQueue' => $tmpGroup['answeredQueue_sum'],
                'avgWaitTime' => $avgWaitTime,
                'avgMissed' => $avgMissed,
                'avgWaitTimeQueue' => $avgWaitTimeQueue,
            ];
        }

        return [
            'data' => $result,
            'recordsTotal' => count($result),
            'recordsFiltered' => count($result),
        ];
    }

    /**
     * curl 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?reportNameID=OutgoingEmployeeCalls&type=json&search=%7B%22dateRangeSelector%22%3A%2221%2F10%2F2024%20-%2021%2F10%2F2024%22%2C%22minBilSec%22%3A%220%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22outgoing-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * curl -H 'Cookie: PHPSESSID=5ada41f50486a5792cb3520f0922b7e9' 'https://boffart.miko.ru/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F10%2F2024+-+31%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22all-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * @return void
     */
    public function exportHistory()
    {
        ini_set('memory_limit', '2024M');
        ini_set('pcre.backtrack_limit', '10000000');
        $reportNameID   = $this->request->get('reportNameID');
        if(ReportSettings::REPORT_QUEUES  === $reportNameID){
            $this->exportHistoryQueue();
            return;
        }
        if(ReportSettings::REPORT_OUTGOING_EMPLOYEE_CALLS  === $reportNameID){
            $this->exportOutgoingEmployeeCalls();
            return;
        }
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if(!is_string($searchPhrase)){
            $this->response->sendRaw();
            return;
        }
        $gr = new GetReport();
        $view = $gr->history($searchPhrase);
        $view->title = urldecode($this->request->get('title')??'');
        if($type === 'json'){
            $this->echoResponse((array)$view);
        }elseif($type === 'pdf'){
            GetReport::exportHistoryPdf($view);
        }elseif ($type === 'xlsx'){
            GetReport::exportHistoryXls($view);
        }
        $this->response->sendRaw();
    }

    /**
     * Скачивание tar архива.
     * https://boffart.miko.ru/pbxcore/api/modules/ModuleExtendedCDRs/downloads?search=%7B%22dateRangeSelector%22%3A%2212%2F09%2F2024%2B-%2B11%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22%22%2C%22additionalFilter%22%3A%22%22%7D
     * @return void
     */
    public function downloads():void
    {
        $searchPhrase   = $this->request->get('search');
        $gr = new GetReport();
        $view = $gr->history($searchPhrase);

        $pathLN = Util::which('ln');
        $tmpDir = '/storage/usbdisk1/mikopbx/tmp/ExportCdr/flist-export-'.microtime(true);
        shell_exec("mkdir -p $tmpDir");
        foreach ($view->data as $baseItem) {
            foreach ($baseItem['4'] as $item){
                if(!file_exists($item['recordingfile'])){
                    continue;
                }
                shell_exec("$pathLN -s {$item['recordingfile']} $tmpDir/{$item['prettyFilename']}.mp3");
            }
        }
        $this->response->setHeader('Content-Description', 'tar file');
        $this->response->setHeader('Content-type', 'application/x-tar');
        $this->response->setHeader('Content-Disposition', "attachment; filename=download-".time().".tar");
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $pathBusybox = Util::which('busybox');
        $this->response->sendRaw();
        passthru("cd $tmpDir; $pathBusybox tar -chf - . 2> /tmp/ar.err" );
        shell_exec($pathBusybox.' rm -rf '.$tmpDir);
    }

    /**
     * curl -H 'Cookie: PHPSESSID=5ada41f50486a5792cb3520f0922b7e9' 'https://boffart.miko.ru/pbxcore/api/modules/ModuleExtendedCDRs/exportOutgoingEmployeeCalls?type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F10%2F2024+-+31%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22all-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * curl 'http://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportOutgoingEmployeeCalls?type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F10%2F2024%20-%2031%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22outgoing-calls%22%2C%22additionalFilter%22%3A%22204%20203%22%7D'
     * @return void
     */
    public function exportOutgoingEmployeeCalls()
    {
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', '10000000');
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if(!is_string($searchPhrase)){
            $this->response->sendRaw();
            return;
        }
        $gr = new GetReport();
        $view = $gr->outgoingEmployeeCalls($searchPhrase);
        $view->title = urldecode($this->request->get('title')??'');
        if($type === 'json'){
            $this->echoResponse((array)$view);
        }elseif($type === 'pdf'){
            GetReport::exportOutgoingEmployeeCallsPrintPdf($view);
        }elseif ($type === 'xlsx'){
            GetReport::exportOutgoingEmployeeCallsPrintXls($view);
        }
        $this->response->sendRaw();
    }
    /**
     * Вывод ответа сервера.
     * @param $result
     * @return void
     */
    private function echoResponse($result):void
    {
        $filename = $result['data']['results']??'';
        if(file_exists($filename)){
            try {
                $result['data']['results'] = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }catch ( \JsonException $e){
                $result['data']['results'] = [];
            }
            unlink($filename);
        }
        try {
            echo json_encode($result, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
        }catch (\Exception $e){
            echo 'Error json encode: '. print_r($result, true);
        }
    }
}