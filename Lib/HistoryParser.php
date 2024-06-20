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

namespace Modules\ModuleExportRecords\Lib;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Providers\CDRDatabaseProvider;
use Modules\ModuleExportRecords\bin\ConnectorDB;
use Modules\ModuleExportRecords\Models\CallHistory;

class HistoryParser
{
    public const LIMIT_CDR = 200;

    /**
     * Заполнение кэш истории звонков. Кто последний говорил с клиентом.
     * @param int $offset
     * @param string $referenceDate
     * @return void
     */
    public static function getHistoryData(int &$offset = 1):array
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
            'group'   => 'linkedid',
            'columns' => 'linkedid',
            'limit'   => self::LIMIT_CDR,
            'add_pack_query' => $add_query,
        ];

        $cdrData = CDRDatabaseProvider::getCdr($filter);
        $resultRows = [];
        if(count($cdrData)>0){
            $newOffset = 0;
            foreach ($cdrData as $cdr){
                $newOffset = (int)$cdr['id'];
                $cdr['srcIndex'] = ConnectorDB::getPhoneIndex($cdr['src_num']);
                $cdr['dstIndex'] = ConnectorDB::getPhoneIndex($cdr['dst_num']);
                if(!isset($resultRows[$cdr['linkedid']])){
                    $srcInner = self::isInnerCdr($cdr, 'src', $innerNumbers);
                    $dstInner = self::isInnerCdr($cdr, 'dst', $innerNumbers);
                    if(($srcInner && !$dstInner) || (stripos( $cdr['src_chan'], 'local/') !== false
                        && stripos( $cdr['dst_chan'], 'pjsip/sip') !== false)){
                        // Автодиалер звонки.
                        $typeCall = CallHistory::CALL_TYPE_OUTGOING;
                        $line = $cdr['to_account'];
                    }elseif($srcInner && ($cdr['is_app'] === '1' || $dstInner)){
                        $typeCall = CallHistory::CALL_TYPE_INNER;
                        $line = '';
                    }else{
                        $typeCall = CallHistory::CALL_TYPE_INCOMING;
                        $line = $cdr['from_account'];
                    }
                    $resultRows[$cdr['linkedid']]['typeCall'] = $typeCall;
                    $resultRows[$cdr['linkedid']]['answered'] = 0;
                    $resultRows[$cdr['linkedid']]['waitTime'] = '';
                    $resultRows[$cdr['linkedid']]['line']     = $line;
                }
                if( $cdr['is_app'] !== '1' && $cdr['billsec'] !== '0' ){
                    $resultRows[$cdr['linkedid']]['answered'] = 1;
                    if($resultRows[$cdr['linkedid']]['waitTime'] === ''){
                        $resultRows[$cdr['linkedid']]['waitTime'] = strtotime($cdr['answer']) - strtotime($cdr['start']);
                    }
                    if (empty($resultRows[$cdr['linkedid']]['line'])
                        && $resultRows[$cdr['linkedid']]['typeCall'] === CallHistory::CALL_TYPE_OUTGOING){
                        $resultRows[$cdr['linkedid']]['line'] = $cdr['to_account'];
                    }
                }
                $resultRows[$cdr['linkedid']]['rows'][] = $cdr;
            }
            $offset = min($offset + self::LIMIT_CDR, $newOffset);
        }

        foreach ($resultRows as $index => $cdr){
            if($cdr['answered'] === 0 && $cdr['typeCall'] === CallHistory::CALL_TYPE_INCOMING){
                $resultRows[$index]['typeCall'] = CallHistory::CALL_TYPE_MISSED;
            }
        }

        return $resultRows;
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