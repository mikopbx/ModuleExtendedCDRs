<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 4 2020
 *
 */

namespace Modules\ModuleExportRecords\Lib\RestAPI\Controllers;

use MikoPBX\Common\Providers\CDRDatabaseProvider;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\Modules\ModulesControllerBase;
use Modules\ModuleExportRecords\Lib\XlsxWriter;
use Modules\ModuleExportRecords\Lib\HistoryParser;

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
     * @return void
     */
    public function downloadsHistory():void
    {
        $startDate  = new \DateTime($_GET['start']??Util::getNowDate());
        $endDate    = new \DateTime($_GET['end']??Util::getNowDate());
        $type = $_GET['type']??'';
        $needBreak = false;
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
        if (empty($_GET['numbers']??'')){
            $numbers=[];
        }else{
            $numbers = explode(  ' ', $_GET['numbers']??'');
        }
        $header = array(
            'Call Type'=>'string',
            'Start'=>'string',
            'Answer Time'=>'string',
            'End Time'=>'string',
            'Src number'=>'string',
            'Dst number'=>'string',
            'DID'=>'string',
            'Bill sec'=>'integer',
            'Duration'=>'integer',
            'Linked Id'=>'string',
            'Unique ID'=>'string',
            'Src Channel'=>'string',
            'Dst Channel'=>'string',
        );
        $writer = new XlsxWriter();
        $writer->writeSheetHeader('cdr', $header);
        foreach ($dateInterval as $interval){
            $filter = [
                'columns' => 'start,answer,src_num,dst_num,src_chan,dst_chan,endtime,linkedid,recordingfile,dialstatus,UNIQUEID,billsec,duration,did',
                'conditions' => 'start BETWEEN :startTime: AND :endTime:',
                'bind' => [
                    'startTime' => $interval['start'],
                    'endTime'   => $interval['end'],
                ],
            ];
            if(!empty($numbers)){
                $filter['conditions'] .= ' AND (src_num IN ({ids:array}) OR dst_num IN ({ids:array}) )';
                $filter['bind']['ids'] = $numbers;
            }

            // Тип звонка определяем по первой CDR.
            $callTypeArray = [];
            $data = CDRDatabaseProvider::getCdr($filter);
            foreach ($data as $cdr){
                if(!isset($callTypeArray[$cdr['linkedid']])) {
                    $srcInner = HistoryParser::isInnerCdr($cdr, 'src');
                    $dstInner = HistoryParser::isInnerCdr($cdr, 'dst');
                    $typeCall = 'in';
                    if($srcInner && $dstInner){
                        $typeCall = 'internal';
                    }elseif ($srcInner && !$dstInner){
                        $typeCall = 'out';
                    }
                    $callTypeArray[$cdr['linkedid']] = $typeCall;
                }else{
                    $typeCall = $callTypeArray[$cdr['linkedid']];
                }
                if($type === 'inner' &&  $typeCall !== 'internal'){
                    continue;
                }
                if ($type === 'out' &&   $typeCall === 'internal'){
                    continue;
                }
                $row = [
                    $typeCall,
                    $cdr['start'],
                    $cdr['answer'],
                    $cdr['endtime'],
                    $cdr['src_num'],
                    $cdr['dst_num'],
                    $cdr['did'],
                    $cdr['billsec'],
                    $cdr['duration'],
                    $cdr['linkedid'],
                    $cdr['UNIQUEID'],
                    $cdr['src_chan'],
                    $cdr['dst_chan'],
                ];
                $writer->writeSheetRow('cdr', $row );
            }
            usleep(300000);
        }
        $this->response->setHeader('Content-Description', 'excel');
        $this->response->setHeader('Content-type', 'application/vnd.ms-excel');
        $this->response->setHeader('Content-Disposition', "attachment; filename=download-".$type."-".time().".xls");
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $this->response->sendRaw();
        $writer->writeToStdOut();
    }

    /**
     * curl --location-trusted -X PUT -H "X-Token: X-Token" -H "X-Token-Secret:X-Token-Secret"  http://127.0.0.1/pbxcore/api/modules/ModuleExportRecords/v1/miko/integrations/test.mp3 -T /storage/usbdisk1/mikopbx/media/custom/blob1699364152657.mp3 -v
     * @param string $company
     * @param string $filename
     * @return void
     */
    public function putTest307(string $company, string $filename):void
    {
        $this->response->redirect("/pbxcore/api/modules/ModuleExportRecords/v1/{$company}/201/{$filename}", true, 307);
    }

    /**
     * @param string $company
     * @param string $filename
     * @return void
     */
    public function putTest201(string $company, string $filename):void
    {
        $headers   = $this->request->getHeaders();
        $resFile = '/storage/usbdisk1/mikopbx/tmp/'.$filename;
        $inputStream = fopen('php://input', 'rb');
        $fileStream  = fopen($resFile, 'wb');
        if ($fileStream && $inputStream) {
            // Копируйте данные из потока в файл
            stream_copy_to_stream($inputStream, $fileStream);
            // Закройте потоки
            fclose($fileStream);
            fclose($inputStream);
            $fSize = trim(shell_exec("soxi $resFile | grep 'File Size' |cut -f 2 -d ':'"));
            $msg = "{$headers['X-Token-Secret']}:{$headers['X-Token']}:$company:$filename:$fSize";
            $code = 201;
            unlink($resFile);
        } else {
            $code = 501;
            $msg = 'Error create stream';
        }

        $this->response->setStatusCode($code, $msg);
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