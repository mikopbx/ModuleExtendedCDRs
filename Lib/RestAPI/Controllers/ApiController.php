<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers;

use MikoPBX\Common\Providers\CDRDatabaseProvider;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;

class ApiController extends ModulesControllerBase
{

    /**
     * @return void
     */
    public function downloads():void
    {
        $startDate  = new \DateTime($_GET['start']??Util::getNowDate());
        $endDate    = new \DateTime($_GET['end']??Util::getNowDate());
        $needBreak = false;
        $type = $_GET['type']??'';

        $dateInterval = [];
        while ($needBreak === false){
            $start = $startDate->format('Y-m-d H:i:s');
            $startDate->modify('+8 days - 1 seconds');
            if($endDate->getTimestamp() <= $startDate->getTimestamp()){
                $startDate = $endDate;
                $needBreak = true;
            }
            $dateInterval[] = [
                'start' => $start,
                'end'   => $startDate->format('Y-m-d H:i:s'),
            ];
            $startDate->modify('+ 1 seconds');
        }
        $pathLN = Util::which('ln');
        $tmpDir = '/storage/usbdisk1/mikopbx/tmp/ExportCdr/flist-export-'.microtime(true);
        shell_exec("mkdir -p $tmpDir");
        foreach ($dateInterval as $interval){
            $filter = [
                'billsec > 0 AND start BETWEEN :startTime: AND :endTime: AND (src_num IN ({ids:array}) OR dst_num IN ({ids:array}) ) ',
                'columns' => 'id,start,answer,src_num,dst_num,src_chan,dst_chan,endtime,linkedid,recordingfile,dialstatus,UNIQUEID',
                'bind' => [
                    'startTime' => $interval['start'],
                    'endTime'   => $interval['end'],
                    'ids' => explode(  ' ', $_GET['numbers']??''),
                ],
                'limit' => 2000
            ];
            $data = CDRDatabaseProvider::getCdr($filter);
            foreach ($data as $cdr){
                $isInner = strpos("{$cdr['src_chan']}.{$cdr['dst_chan']}", 'SIP-') === false;
                if($type === 'inner' && !$isInner){
                    continue;
                }
                if ($type === 'out' && $isInner){
                    continue;
                }
                $dateFormatter =  new \DateTime($cdr['start']);
                $startCall = $dateFormatter->format('Y-m-d_H-i-s');
                $newFilename = "{$startCall}__{$cdr['src_num']}-{$cdr['dst_num']}_".basename($cdr['recordingfile']);
                shell_exec("$pathLN -s {$cdr['recordingfile']} $tmpDir/$newFilename");
            }
        }

        $this->response->setHeader('Content-Description', 'tar file');
        $this->response->setHeader('Content-type', 'application/x-tar');
        $this->response->setHeader('Content-Disposition', "attachment; filename=download-".$type."-".time().".tar");
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $pathBusybox = Util::which('busybox');
        $this->response->sendRaw();
        passthru("cd $tmpDir; $pathBusybox tar -chf - . 2> /tmp/ar.err" );
        shell_exec($pathBusybox.' rm -rf '.$tmpDir);
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