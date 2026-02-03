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

namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Providers\CDRDatabaseProvider;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\SystemMessages;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerCdr;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Models\CallHistory;

class HistoryParser
{
    public const LIMIT_CDR = 100;
    public const CDR_SYNC_PROGRESS_KEY = "cdrSyncProgress";

    /**
     * Retrieves all completed temporary CDRs.
     * @param array $filter  An array of filter parameters.
     * @return array An array of CDR data.
     */
    public static function getCdr(array $filter = []): array
    {
        if (empty($filter)) {
            $filter = [
                'work_completed<>1 AND endtime<>""',
                'miko_tmp_db' => true,
                'limit' => 2000
            ];
        }
        $filter['miko_result_in_file'] = true;
        if(!isset($filter['order'])){
            $filter['order'] = 'answer';
        }
        if (!isset($filter['columns'])) {
            $filter['columns'] = 'id,start,answer,src_num,dst_num,dst_chan,endtime,linkedid,recordingfile,dialstatus,UNIQUEID';
        }

        $client = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        $filename = '';
        try {
            [$result, $message] = $client->sendRequest(json_encode($filter), 30);
            if ($result!==false){
                $filename = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\Throwable $e) {
            $filename = '';
        }
        $result_data = [];
        if (is_string($filename) && file_exists($filename)) {
            try {
                $result_data = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                SystemMessages::sysLogMsg('HistoryParser:SELECT_CDR_TUBE', 'Error parse response.');
            }

            $di = MikoPBXVersion::getDefaultDi();
            if($di !== null){
                $findPath = Util::which('find');
                $downloadCacheDir = $di->getShared('config')->path('www.downloadCacheDir');
                shell_exec("$findPath -L $downloadCacheDir -samefile  $filename -delete");
            }
            unlink($filename);
        }

        return $result_data;
    }

    public static function getQueues():array
    {
        $queues = CacheManager::getCacheData('ModuleMtsPbx');
        if(empty($queues)){
            $queues = [];
            $queuesData = CallQueues::find(['columns' => 'uniqid,name,extension']);
            foreach ($queuesData as $queue) {
                $queues[$queue->extension] = $queue->uniqid;
            }
            CacheManager::setCacheData('ModuleMtsPbx', $queues, 120);
        }
        return $queues;
    }

    /**
     * Заполнение кэш истории звонков. Кто последний говорил с клиентом.
     * @param int $offset
     * @return void
     */
    public static function getHistoryData(int $offset = 1):array
    {
        $filter = [
            "type = :extType:",
            'columns'   => 'number',
            'bind'  => [
                'extType' => Extensions::TYPE_SIP
            ]
        ];
        $innerNumbers = array_column(Extensions::find($filter)->toArray(), 'number');
        unset($filter);

        $add_query                     = [
            'linkedid IN ({linkedid:array})',
            'bind'    => [
                'linkedid' => null,
            ],
            'order'   => 'start,id',
        ];
        $filter                        = [
            'id>:id: AND linkedid <> :linkedid:',
            'bind'    => [
                'id'  => $offset,
                'linkedid' => '',
            ],
            'order'   => 'id ASC',
            'group'   => 'linkedid',
            'columns' => 'linkedid',
            'limit'   => self::LIMIT_CDR,
            'add_pack_query' => $add_query,
        ];

        $cdrData = self::getCdr($filter);
        $resultRows = [];
        if(count($cdrData)>0){
            $queues = self::getQueues();

            $newOffset = 0;
            $minNewOffset = 0;
            foreach ($cdrData as $cdr){
                foreach (['id', 'is_app', 'billsec'] as $key){
                    $cdr[$key] = intval($cdr[$key]);
                }

                if($minNewOffset === 0 ){
                    $minNewOffset = $cdr['id'];
                }else{
                    $minNewOffset = min($cdr['id'], $minNewOffset);
                }
                $newOffset = max($cdr['id'], $newOffset);
                $cdr['srcIndex'] = ConnectorDB::getPhoneIndex($cdr['src_num']);
                $cdr['dstIndex'] = ConnectorDB::getPhoneIndex($cdr['dst_num']);

                $srcInner = self::isInnerCdr($cdr, 'src', $innerNumbers);
                $dstInner = self::isInnerCdr($cdr, 'dst', $innerNumbers);
                if(!isset($resultRows[$cdr['linkedid']])){
                    if(($srcInner && !$dstInner) || (stripos( $cdr['src_chan'], 'local/') !== false
                        && stripos( $cdr['dst_chan'], 'pjsip/sip') !== false)){
                        // Автодиалер звонки.
                        $typeCall = CallHistory::CALL_TYPE_OUTGOING;
                    }elseif($srcInner && ($cdr['is_app'] === 1 || $dstInner)){
                        $typeCall = CallHistory::CALL_TYPE_INNER;
                    }else{
                        $typeCall = CallHistory::CALL_TYPE_INCOMING;
                    }
                    $resultRows[$cdr['linkedid']]['typeCall']   = $typeCall;
                    $resultRows[$cdr['linkedid']]['answered']   = 0;
                    $resultRows[$cdr['linkedid']]['waitTime']   = '';
                    $resultRows[$cdr['linkedid']]['firstQueue'] = [];
                    $resultRows[$cdr['linkedid']]['q_start']   = strtotime($cdr['start']);
                    $resultRows[$cdr['linkedid']]['q_endtime'] = strtotime($cdr['endtime']);
                    $resultRows[$cdr['linkedid']]['q_answer']  = strtotime($cdr['answer']);
                }else{
                    $resultRows[$cdr['linkedid']]['q_start']   = min(strtotime($cdr['start']),   $resultRows[$cdr['linkedid']]['q_start']);
                    $resultRows[$cdr['linkedid']]['q_endtime'] = max(strtotime($cdr['endtime']), $resultRows[$cdr['linkedid']]['q_endtime']);
                }

                $line = $resultRows[$cdr['linkedid']]['line']??'';
                if(empty($line)){
                    if(($srcInner && !$dstInner) || (stripos( $cdr['src_chan'], 'local/') !== false
                            && stripos( $cdr['dst_chan'], 'pjsip/sip') !== false)){
                        // Автодиалер звонки.
                        $line = $cdr['to_account'];
                    }elseif($srcInner && ($cdr['is_app'] === 1 || $dstInner)){
                        $line = '';
                    }else{
                        $line = $cdr['from_account'];
                    }
                    $resultRows[$cdr['linkedid']]['line']     = $line;
                }

                $firstQueue = &$resultRows[$cdr['linkedid']]['firstQueue'];
                if(stripos( $cdr['dst_chan'], 'app:') !== false && !empty($firstQueue) && $firstQueue['ended'] === 0){
                    // Следующие приложения этого звонкаю
                    // Если прошлая очередь не была завершена / отвечена, то завершим ее обработку.
                    $firstQueue['ended'] = 1;
                    $firstQueue['queueWait'] = max(strtotime($cdr['start']) - strtotime($firstQueue['start']), 0);
                }elseif(stripos( $cdr['dst_chan'], 'queue:') !== false){
                    $queueId = $queues[$cdr['dst_num']]??'';
                    if(empty($firstQueue)){
                        // Первая очередь в этом звонке.
                        $firstQueue = [
                            'id'        => $queueId,
                            'number'    => $cdr['dst_num'],
                            'start'     => $cdr['start'],
                            'queueWait' => 0,
                            'answered'   => 0,
                            'ended'     => 0
                        ];
                    }elseif($firstQueue['ended'] === 0 && $queueId !== $firstQueue['id']){
                        // Следующие очереди этого звонкаю
                        // Если прошлая очередь не была завершена / отвечена, то завершим ее обработку.
                        // Исключаем повторный звонок на эту же очередь
                        $firstQueue['ended'] = 1;
                        $firstQueue['queueWait'] = max(strtotime($cdr['start']) - strtotime($firstQueue['start']), 0);
                    }
                }elseif (!empty($firstQueue) && $firstQueue['ended'] === 0){
                    // Вычисляем время ожидания первой очереди, если она не была завершена.
                    // Не заполняем ended.
                    $queueWait = max(strtotime($cdr['endtime']) - strtotime($firstQueue['start']), 0);
                    $firstQueue['queueWait'] = max($queueWait, $firstQueue['queueWait']);
                }

                if( $cdr['is_app'] !== 1 && $cdr['billsec'] !== 0 ){
                    $resultRows[$cdr['linkedid']]['q_answer']   = max(strtotime($cdr['answer']), $resultRows[$cdr['linkedid']]['q_answer']);
                    $resultRows[$cdr['linkedid']]['answered'] = 1;
                    if($resultRows[$cdr['linkedid']]['waitTime'] === ''){
                        $resultRows[$cdr['linkedid']]['waitTime'] = max(strtotime($cdr['answer']) - strtotime($cdr['start']), 0);
                    }
                    if (empty($resultRows[$cdr['linkedid']]['line'])
                        && $resultRows[$cdr['linkedid']]['typeCall'] === CallHistory::CALL_TYPE_OUTGOING){
                        $resultRows[$cdr['linkedid']]['line'] = $cdr['to_account'];
                    }

                    if(!empty($firstQueue)
                        && $firstQueue['answered'] === 0 && $firstQueue['ended'] === 0){
                        // Вызов отвечен. Вычисляем время ожидания очереди и заполняем ended.
                        $firstQueue['queueWait'] = max(strtotime($cdr['answer']) - strtotime($firstQueue['start']), 0);
                        $firstQueue['answered'] = 1;
                        $firstQueue['ended'] = 1;
                    }
                }
                unset($firstQueue);
                $resultRows[$cdr['linkedid']]['rows'][] = $cdr;
            }
            $calculatedOffset = min($offset + self::LIMIT_CDR, $newOffset);
            $calculatedOffset = max($calculatedOffset, $minNewOffset);
        }

        foreach ($resultRows as $index => $cdr){
            if($cdr['answered'] === 0 && $cdr['typeCall'] === CallHistory::CALL_TYPE_INCOMING){
                $resultRows[$index]['typeCall'] = CallHistory::CALL_TYPE_MISSED;
            }

            foreach (['start', 'endtime', 'answer'] as $key){
                if(empty($cdr[$key]) || !is_numeric($cdr[$key])){
                    continue;
                }
                $resultRows[$index][$key] = date('Y-m-d H:i:s', $cdr[$key]);
            }
        }

        return ['data' => $resultRows, 'newOffset' => $calculatedOffset ?? $offset];
    }

    /**
     * Возвращает данные последней CDR ID и START.
     * @return array
     */
    public static function getLastCdrData():array
    {
        $filter = [
            'columns' => 'id,start',
            'order' => 'id DESC',
            'limit' => 1,
        ];
        $res = \Modules\ModuleExtendedCDRs\Lib\HistoryParser::getCdr($filter);
        return $res[0]??[];
    }

    /**
     * Определяет, является ли номер в CDR внутренним.
     * @param array $cdr
     * @param string $fieldName
     * @param array $innerNumbers
     * @return bool
     */
    public static function isInnerCdr(array $cdr, string $fieldName, array $innerNumbers):bool{
        $number  = $cdr["{$fieldName}_num"];
        $channel = $cdr["{$fieldName}_chan"];
        if(empty($channel) && in_array($number, $innerNumbers, true)){
            return true;
        }
        if(mb_strlen($number) > 4 && !in_array($number, $innerNumbers, true)){
            return false;
        }
        return is_numeric($number) && strpos($channel, "/{$number}-") !== false;
    }

    /**
     * Возвращает минимальное значение ID
     * @return int
     */
    public static function getMinCdrId():int
    {
        $filter = [
            'columns' => 'id',
            'limit'   => 1,
            'order' => 'id ASC'
        ];
        $cdrData = CDRDatabaseProvider::getCdr($filter);
        $id = (int)($cdrData[0]['id']??0);
        if($id > 0){
            $id--;
        }
        return $id;
    }
}