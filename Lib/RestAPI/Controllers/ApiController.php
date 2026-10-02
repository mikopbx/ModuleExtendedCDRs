<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Directories;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Lib\DownloadHeaderPolicy;
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Modules\ModuleExtendedCDRs\Lib\RecordingPathPolicy;
use Modules\ModuleExtendedCDRs\Lib\ReportSearchPolicy;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;

class ApiController extends ModulesControllerBase
{

    /**
     * Export detailed history. This private route requires normal API authentication.
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
     * Prefer CallRecordID. The legacy view parameter remains supported only
     * for paths validated inside the configured recordings directory.
     */
    public function recordsAction(): void
    {
        $id = (string) $this->request->get('CallRecordID');
        $candidate = '';
        if ($id !== '') {
            $resolved = ConnectorDB::invoke('getRecordingPathByID', [$id]);
            $candidate = is_array($resolved) ? (string) ($resolved[0] ?? '') : '';
        } else {
            $candidate = (string) $this->request->get('view');
        }

        try {
            $policy = new RecordingPathPolicy(Directories::getDir(Directories::AST_MONITOR_DIR));
            $result = $policy->validate($candidate);
        } catch (\Throwable $exception) {
            Util::sysLogMsg('ModuleExtendedCDRs', 'event=recording_rejected reason=policy_unavailable endpoint=records');
            $this->sendError(500);
            return;
        }

        if (!$result->isAllowed() || $result->path() === null) {
            Util::sysLogMsg(
                'ModuleExtendedCDRs',
                'event=recording_rejected reason=' . $result->reason() . ' endpoint=records'
            );
            $this->sendError($result->status());
            return;
        }

        $fp = fopen($result->path(), 'rb');
        if ($fp === false) {
            $this->sendError(404);
            return;
        }

        try {
            $size = filesize($result->path());
            $this->response->setHeader('Content-Disposition', DownloadHeaderPolicy::attachment((string) $result->downloadName()));
            $this->response->setHeader('Content-Type', (string) $result->mimeType());
            $this->response->setHeader('Content-Transfer-Encoding', 'binary');
            $this->response->setHeader('X-Content-Type-Options', 'nosniff');
            if ($size !== false) {
                $this->response->setContentLength($size);
            }
            $this->response->sendHeaders();
            fpassthru($fp);
        } finally {
            fclose($fp);
        }
    }


    /**
     * Export aggregated queue history for an authenticated request.
     * @return void
     */
    public function exportHistoryQueue(): void
    {
        ini_set('memory_limit', '2024M');
        ini_set('pcre.backtrack_limit', '10000000');
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if (!is_string($searchPhrase) || !ReportSearchPolicy::isValid($searchPhrase)) {
            $this->sendError(400);
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
                    'queueId' =>  ($queueId==='')?'-':$queueId,
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
            $avgMissed        = $uniqueCalls > 0 ? round($tmpGroup['missed_sum'] / $uniqueCalls * 100, 2) : 0;
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
     * Export call history for an authenticated request.
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
        if (!is_string($searchPhrase) || !ReportSearchPolicy::isValid($searchPhrase)) {
            $this->sendError(400);
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

    /** Legacy preparation URL: returns the same asynchronous job as the new button. */
    public function downloads(): void { $this->archivePrepare(); }

    public function archivePrepare(): void
    {
        $search=$this->request->getPost('search') ?? $this->request->get('search');
        if (!is_string($search) || strlen($search)>32768 || !ReportSearchPolicy::isValid($search)) {
            $this->sendError(400); return;
        }
        try {
            $input=json_decode($search,true);
            $input['dateRangeSelector']=(new GetReport())->getDateRanges($input['dateRangeSelector']);
            ksort($input);
            $acl=$this->archiveAcl();
            $owner=$this->archiveOwner($acl);
            $job=\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::jobs()->request($owner,
                ['search'=>json_encode($input,JSON_UNESCAPED_SLASHES),'acl'=>$acl],
                \Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::revision(),
                [\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::class,'launch']);
            $this->archiveJson($job,$owner);
        } catch (\Throwable $e) { $this->archiveFailure($e); }
    }

    public function archiveStatus(): void
    {
        try {
            $owner=$this->archiveOwner($this->archiveAcl());
            $job=\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::jobs()->status((string)$this->request->get('id'),$owner);
            $this->archiveJson($job,$owner);
        } catch (\Throwable $e) { $this->archiveFailure($e); }
    }

    /** Native browser POST. No general API access: a two-minute job-specific capability is mandatory. */
    public function archiveFile(): void
    {
        try {
            $id=(string)$this->request->getPost('id');
            $ticket=(string)$this->request->getPost('ticket');
            $fp=\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::jobs()->openDownload($id,$ticket);
        } catch (\Throwable $e) { $this->sendError(403); return; }
        try {
            $this->response->setHeader('Content-Type','application/x-tar');
            $this->response->setHeader('Content-Disposition',DownloadHeaderPolicy::attachment('recordings.tar'));
            $this->response->setHeader('Cache-Control','private, no-store');
            $this->response->setHeader('X-Content-Type-Options','nosniff');
            $this->response->setHeader('X-Accel-Buffering','no');
            $stat=fstat($fp);
            $this->response->setContentLength($stat['size']);
            $this->response->sendHeaders();
            fpassthru($fp);
        } finally { fclose($fp); }
    }

    private function archiveAcl(): array
    {
        $acl=['conditions'=>'1=1','bind'=>[]];
        $jwt=method_exists($this->request,'getJwtPayload') ? ($this->request->getJwtPayload()??[]) : [];
        $context=[];
        if ($jwt) {
            $context=['auth_type'=>'bearer_token','user_name'=>$jwt['userId']??null,
                'session_id'=>$jwt['userId']??null,'role'=>$jwt['role']??null];
        }
        \MikoPBX\Common\Providers\PBXConfModulesProvider::hookModulesMethod(
            \MikoPBX\Modules\Config\CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY,[&$acl,$context]);
        return ['conditions'=>$acl['conditions'],'bind'=>$acl['bind']??[]];
    }

    private function archiveOwner(array $acl): string
    {
        $jwt=method_exists($this->request,'getJwtPayload') ? $this->request->getJwtPayload() : null;
        if (is_array($jwt) && isset($jwt['userId'])) {
            $identity=json_encode([$jwt['userId'],$jwt['role']??'']);
        } else {
            $identity=$this->request->getHeader('Authorization');
            if ($identity==='') $identity=$this->request->getHeader('Cookie');
            if ($identity==='' && $this->request->getClientAddress()==='127.0.0.1') $identity='local-core';
            if ($identity==='') throw new \RuntimeException('archive_not_found');
        }
        return hash('sha256',$identity.json_encode($acl));
    }

    private function archiveJson(array $job,string $owner): void
    {
        if ($job['state']==='ready') {
            $job['ticket']=\Modules\ModuleExtendedCDRs\Lib\RecordingArchiveService::jobs()->ticket($job['id'],$owner);
        }
        $this->response->setHeader('Content-Type','application/json');
        $this->response->setHeader('Cache-Control','no-store');
        $this->echoResponse($job);
        $this->response->sendRaw();
    }

    private function archiveFailure(\Throwable $e): void
    {
        $code=$e->getMessage();
        $known=['archive_not_found','archive_expired','archive_queue_full','archive_start_failed'];
        if (!in_array($code,$known,true)) $code='archive_build_failed';
        $this->response->setStatusCode(in_array($code,['archive_not_found','archive_expired'],true)?404:503);
        $this->response->setHeader('Content-Type','application/json');
        $this->response->setHeader('Cache-Control','no-store');
        $this->echoResponse(['error'=>$code]);
        $this->response->sendRaw();
    }

    /**
     * Export employee call totals for an authenticated request.
     * @return void
     */
    public function exportOutgoingEmployeeCalls()
    {
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', '10000000');
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if (!is_string($searchPhrase) || !ReportSearchPolicy::isValid($searchPhrase)) {
            $this->sendError(400);
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
