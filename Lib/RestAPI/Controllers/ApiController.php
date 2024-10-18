<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;

class ApiController extends ModulesControllerBase
{

    /**
     * curl 'https://127.0.0.1/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?reportNameID=OutgoingEmployeeCalls&type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F10%2F2024+-+31%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22all-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * curl -H 'Cookie: PHPSESSID=5ada41f50486a5792cb3520f0922b7e9' 'https://boffart.miko.ru/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?type=json&search=%7B%22dateRangeSelector%22%3A%2201%2F10%2F2024+-+31%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22all-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * @return void
     */
    public function exportHistory()
    {
        $reportNameID   = $this->request->get('reportNameID');
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
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        if(!is_string($searchPhrase)){
            $this->response->sendRaw();
            return;
        }
        $gr = new GetReport();
        $view = $gr->outgoingEmployeeCalls($searchPhrase);
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