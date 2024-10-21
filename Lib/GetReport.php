<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2024 Alexey Portnov and Nikolay Beketov
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

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Providers\PBXConfModulesProvider;
use MikoPBX\Modules\Config\CDRConfigInterface;
use Modules\ModuleUsersGroups\Models\GroupMembers;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use stdClass;
use DateTime;
use MikoPBX\Common\Models\Sip;
use MikoPBX\Core\System\Util;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Models\CallHistory;

require_once(dirname(__DIR__) . '/vendor/autoload.php');

class GetReport
{
    /**
     * Формирование журнала звонков.
     * @param string   $searchPhrase
     * @param int|null $offset
     * @param int|null $limit
     * @return stdClass
     */
    public function history(string $searchPhrase = '', ?int $offset = null, ?int $limit = null): stdClass
    {
        $tmpSearchPhrase = json_decode($searchPhrase, true);
        $tmpSearchPhrase['dateRangeSelector'] = self::getDateRanges($tmpSearchPhrase['dateRangeSelector']);
        $searchPhrase = json_encode($tmpSearchPhrase, JSON_UNESCAPED_SLASHES);
        unset($tmpSearchPhrase);

        $view = (object)[
            'recordsFiltered' => 0,
            'data' => [],
            'searchPhrase' => $searchPhrase,
        ];

        $parameters = [];
        $parameters['columns'] = 'COUNT(DISTINCT(linkedid)) as rows';
        // Count the number of unique calls considering filters
        if (empty($searchPhrase)) {
            return $view;
        }
        [$start, $end, $numbers, $additionalNumbers] = $this->prepareConditionsForSearchPhrases(
            $searchPhrase,
            $parameters
        );
        // If we couldn't understand the search phrase, return empty result
        if (empty($parameters['conditions'])) {
            $view->conditions = 'empty';
            return $view;
        }
        $additionalFilter = [];
        if (php_sapi_name() !== 'cli') {
            PBXConfModulesProvider::hookModulesMethod(
                CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY,
                [&$additionalFilter]
            );
        }
        $view->additionalFilter = $additionalFilter;
        $view->baseNumberFilter = array_merge($numbers, $additionalNumbers, $additionalFilter);

        $recordsFilteredReq = ConnectorDB::invoke(
            'getCountCdr',
            [$start, $end, $numbers, $additionalNumbers, $additionalFilter]
        );
        $view->recordsFiltered = $recordsFilteredReq['cCalls'] ?? 0;
        $view->recordsInner = $recordsFilteredReq['cINNER'] ?? 0;
        $view->recordsOutgoing = $recordsFilteredReq['cOUTGOING'] ?? 0;
        $view->recordsIncoming = $recordsFilteredReq['cINCOMING'] ?? 0;
        $view->recordsMissed = $recordsFilteredReq['cMISSED'] ?? 0;

        // Find all LinkedIDs that match the specified filter
        $parameters['columns'] = 'DISTINCT(linkedid) as linkedid';
        $parameters['order'] = ['start desc'];

        if (!empty($limit)) {
            $parameters['limit'] = $limit;
        }
        if (!empty($offset)) {
            $parameters['offset'] = $offset;
        }

        $selectedLinkedIds = $this->selectCDRRecordsWithFilters($parameters);
        $arrIDS = [];
        foreach ($selectedLinkedIds as $item) {
            $arrIDS[] = $item['linkedid'];
        }
        if (empty($arrIDS)) {
            return $view;
        }

        // Retrieve all detailed records for processing and merging
        if (count($arrIDS) === 1) {
            $parameters = [
                'conditions' => 'linkedid = :ids:',
                'columns' => 'id,disposition,start,answer,endtime,src_num,dst_num,billsec,recordingfile,did,dst_chan,linkedid,is_app,verbose_call_id,typeCall,waitTime,line,stateCall',
                'bind' => [
                    'ids' => $arrIDS[0],
                ],
                'order' => ['linkedid desc', 'start asc', 'id asc'],
            ];
        } else {
            $parameters = [
                'conditions' => 'linkedid IN ({ids:array})',
                'columns' => 'id,disposition,start,answer,endtime,src_num,dst_num,billsec,recordingfile,did,dst_chan,linkedid,is_app,verbose_call_id,typeCall,waitTime,line,stateCall',
                'bind' => [
                    'ids' => $arrIDS,
                ],
                'order' => ['linkedid desc', 'start asc', 'id asc'],
            ];
        }

        $selectedRecords = $this->selectCDRRecordsWithFilters($parameters);
        $arrCdr = [];
        $objectLinkedCallRecord = (object)[
            'linkedid' => '',
            'disposition' => '',
            'start' => '',
            'endtime' => '',
            'src_num' => '',
            'dst_num' => '',
            'billsec' => 0,
            'typeCall' => '',
            'stateCall' => '',
            'waitTime' => '',
            'line' => '',
            'answered' => [],
            'detail' => [],
            'ids' => [],
            'did' => ''
        ];

        $providers = Sip::find("type='friend'");
        $providerName = [];
        foreach ($providers as $provider) {
            $providerName[$provider->uniqid] = $provider->description;
        }
        unset($providers);

        $statsCall = [
            CallHistory::CALL_STATE_OK => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_OK', false),
            CallHistory::CALL_STATE_TRANSFER => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_TRANSFER', false),
            CallHistory::CALL_STATE_MISSED => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_MISSED', false),
            CallHistory::CALL_STATE_OUTGOING_FAIL => Util::translate(
                'repModuleExtendedCDRs_cdr_CALL_STATE_OUTGOING_FAIL',
                false
            ),
            CallHistory::CALL_STATE_RECALL_CLIENT => Util::translate(
                'repModuleExtendedCDRs_cdr_CALL_STATE_RECALL_CLIENT',
                false
            ),
            CallHistory::CALL_STATE_RECALL_USER => Util::translate(
                'repModuleExtendedCDRs_cdr_CALL_STATE_RECALL_USER',
                false
            ),
            CallHistory::CALL_STATE_APPLICATION => Util::translate(
                'repModuleExtendedCDRs_cdr_CALL_STATE_APPLICATION',
                false
            ),
        ];
        $typeCallNames = [
            CallHistory::CALL_TYPE_INNER => Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_INNER', false),
            CallHistory::CALL_TYPE_OUTGOING => Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_OUTGOING', false),
            CallHistory::CALL_TYPE_INCOMING => Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_INCOMING', false),
            CallHistory::CALL_TYPE_MISSED => Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_MISSED', false),
        ];

        foreach ($selectedRecords as $arrRecord) {
            $record = (object)$arrRecord;
            if (!array_key_exists($record->linkedid, $arrCdr)) {
                $arrCdr[$record->linkedid] = clone $objectLinkedCallRecord;
            }
            if ($record->is_app !== '1' && $record->billsec > 0 && ($record->disposition === 'ANSWER' || $record->disposition === 'ANSWERED')) {
                $disposition = 'ANSWERED';
            } else {
                $disposition = 'NOANSWER';
            }
            $linkedRecord = $arrCdr[$record->linkedid];
            $linkedRecord->linkedid = $record->linkedid;
            $linkedRecord->typeCall = $record->typeCall;
            $linkedRecord->typeCallDesc = $typeCallNames[$record->typeCall];
            $linkedRecord->waitTime = 1 * $record->waitTime;
            $linkedRecord->line = $providerName[$record->line] ?? $record->line;
            $linkedRecord->disposition = $linkedRecord->disposition !== 'ANSWERED' ? $disposition : 'ANSWERED';
            $linkedRecord->start = $linkedRecord->start === '' ? $record->start : $linkedRecord->start;
            if ($record->stateCall === CallHistory::CALL_STATE_OK) {
                $linkedRecord->stateCall = $statsCall[$record->stateCall];
                $linkedRecord->stateCallIndex = $record->stateCall;
            } elseif ($record->stateCall !== CallHistory::CALL_STATE_APPLICATION) {
                $linkedRecord->stateCall = ($linkedRecord->stateCall === '') ? $statsCall[$record->stateCall] : $linkedRecord->stateCall;
                $linkedRecord->stateCallIndex = $linkedRecord->stateCall === '' ? $record->stateCall : $linkedRecord->stateCall;
            }

            if ($linkedRecord->typeCall === CallHistory::CALL_TYPE_INCOMING) {
                $linkedRecord->did = ($linkedRecord->did === '') ? $record->did : $linkedRecord->did;
            }

            $linkedRecord->endtime = max($record->endtime, $linkedRecord->endtime);
            $linkedRecord->src_num = $linkedRecord->src_num === '' ? $record->src_num : $linkedRecord->src_num;
            $linkedRecord->dst_num = $linkedRecord->dst_num === '' ? $record->dst_num : $linkedRecord->dst_num;
            $linkedRecord->billsec += (int)$record->billsec;
            $isAppWithRecord = ($record->is_app === '1' && file_exists($record->recordingfile));
            if ($disposition === 'ANSWERED' || $isAppWithRecord) {
                if ($record->is_app === '1') {
                    $waitTime = 0;
                } else {
                    $waitTime = strtotime($record->answer) - strtotime($record->start);
                }

                $formattedDate = date('Y-m-d-H_i', strtotime($linkedRecord->start));
                $uid = str_replace('mikopbx-', '', $linkedRecord->linkedid);
                $prettyFilename = "$uid-$formattedDate-$linkedRecord->src_num-$linkedRecord->dst_num";
                $linkedRecord->answered[] = [
                    'id' => $record->id,
                    'start' => date('d-m-Y H:i:s', strtotime($record->start)),
                    'waitTime' => gmdate($waitTime < 3600 ? 'i:s' : 'G:i:s', $waitTime),
                    'billsec' => gmdate($record->billsec < 3600 ? 'i:s' : 'G:i:s', $record->billsec),
                    'billSecInt' => $record->billsec,
                    'src_num' => $record->src_num,
                    'dst_num' => $record->dst_num,
                    'recordingfile' => $record->recordingfile,
                    'prettyFilename' => $prettyFilename,
                    'stateCall' => $statsCall[$record->stateCall],
                    'stateCallIndex' => $record->stateCall,
                ];
            } else {
                $waitTime = strtotime($record->endtime) - strtotime($record->start);
                $linkedRecord->answered[] = [
                    'id' => $record->id,
                    'start' => date('d-m-Y H:i:s', strtotime($record->start)),
                    'waitTime' => gmdate($waitTime < 3600 ? 'i:s' : 'G:i:s', $waitTime),
                    'billsec' => gmdate('i:s', 0),
                    'billSecInt' => 0,
                    'src_num' => $record->src_num,
                    'dst_num' => $record->dst_num,
                    'recordingfile' => '',
                    'prettyFilename' => '',
                    'stateCall' => $statsCall[$record->stateCall],
                    'stateCallIndex' => $record->stateCall,
                ];
            }
            $linkedRecord->detail[] = $record;
            if (!empty($record->verbose_call_id)) {
                $linkedRecord->ids[] = $record->verbose_call_id;
            }
        }
        $output = [];
        foreach ($arrCdr as $cdr) {
            $timing = gmdate($cdr->billsec < 3600 ? 'i:s' : 'G:i:s', $cdr->billsec);
            if ('ANSWERED' !== $cdr->disposition) {
                // Это пропущенный.
                $cdr->waitTime = strtotime($cdr->endtime) - strtotime($cdr->start);
            }
            $additionalClass = (empty($cdr->answered)) ? 'ui' : 'detailed';
            $output[] = [
                date('d-m-Y H:i:s', strtotime($cdr->start)),
                $cdr->src_num,
                $cdr->dst_num,
                $timing === '00:00' ? '' : $timing,
                $cdr->answered,
                $cdr->disposition,
                'waitTime' => gmdate($cdr->waitTime < 3600 ? 'i:s' : 'G:i:s', $cdr->waitTime),
                'stateCall' => $cdr->stateCall,
                'typeCall' => $cdr->typeCall,
                'typeCallDesc' => $cdr->typeCallDesc,
                'line' => $cdr->line,
                'did' => $cdr->did,
                'billsec' => $cdr->billsec,
                'DT_RowId' => $cdr->linkedid,
                'DT_RowClass' => trim($additionalClass . ' ' . ('NOANSWER' === $cdr->disposition ? 'negative' : '')),
                'ids' => rawurlencode(implode('&', array_unique($cdr->ids))),
            ];
        }

        $view->data = $output;
        return $view;
    }

    public static function exportHistoryXls($view): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            Util::translate('repModuleExtendedCDRs_cdr_ColumnTypeState'),
            Util::translate('cdr_ColumnDate'),
            Util::translate('cdr_ColumnFrom'),
            Util::translate('cdr_ColumnTo'),
            Util::translate('repModuleExtendedCDRs_cdr_ColumnLine'),
            Util::translate('repModuleExtendedCDRs_cdr_ColumnWaitTime'),
            Util::translate('cdr_ColumnDuration'),
            Util::translate('repModuleExtendedCDRs_cdr_ColumnCallState'),
            'id'
        ];
        $sheet->fromArray($headers, null, 'A1');
        $rowIndex = 2;
        foreach ($view->data as $baseItem) {
            foreach ($baseItem['4'] as $item) {
                $sheet->setCellValue('A' . $rowIndex, htmlspecialchars($baseItem['typeCallDesc']));
                $sheet->setCellValue('B' . $rowIndex, htmlspecialchars($item['start']));
                $sheet->setCellValue('C' . $rowIndex, htmlspecialchars($item['src_num']));
                $sheet->setCellValue('D' . $rowIndex, htmlspecialchars($item['dst_num']));
                $sheet->setCellValue('E' . $rowIndex, htmlspecialchars($baseItem['line']));
                $sheet->setCellValue('F' . $rowIndex, htmlspecialchars($item['waitTime']));
                $sheet->setCellValue('G' . $rowIndex, htmlspecialchars($item['billsec']));
                $sheet->setCellValue('H' . $rowIndex, htmlspecialchars($item['stateCall']));
                $sheet->setCellValue('I' . $rowIndex, htmlspecialchars($baseItem['DT_RowId']));
                $rowIndex++;
            }
        }
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $maxLength = 0;
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($cellValue !== null) {
                    $maxLength = max($maxLength, strlen($cellValue));
                }
            }
            $sheet->getColumnDimensionByColumn($col)->setWidth($maxLength + 2);
        }
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="calls_report.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    public static function exportHistoryPdf($view, $saveInFile = false): string
    {
        $tmpDir = '/tmp';
        $mpdf = new Mpdf(['tempDir' => $tmpDir]);
        $html = '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">';
        $html .= '<tr>';

        $html .= '<thead>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_cdr_ColumnTypeState'
            ) . '</th>' . '<th>' . Util::translate('cdr_ColumnDate') . '</th>' . '<th>' . Util::translate(
                'cdr_ColumnFrom'
            ) . '</th>' . '<th>' . Util::translate('cdr_ColumnTo') . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_cdr_ColumnLine'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_cdr_ColumnWaitTime'
            ) . '</th>' . '<th>' . Util::translate('cdr_ColumnDuration') . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_cdr_ColumnCallState'
            ) . '</th>' . '<th>id</th>' . '</thead>';
        $html .= '<tbody>';

        foreach ($view->data as $baseItem) {
            foreach ($baseItem['4'] as $item) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($baseItem['typeCallDesc']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['start']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['src_num']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['dst_num']) . '</td>';
                $html .= '<td>' . htmlspecialchars($baseItem['line']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['waitTime']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['billsec']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['stateCall']) . '</td>';
                $html .= '<td>' . htmlspecialchars($baseItem['DT_RowId']) . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
        $filename = $tmpDir . '/calls_report-' . time() . '.pdf';

        if ($saveInFile === true) {
            $mpdf->Output($tmpDir . '/calls_report-' . time() . '.pdf', Destination::FILE);
        } else {
            $mpdf->Output('calls_report.pdf', Destination::DOWNLOAD);
        }

        return $filename;
    }

    /**
     * Формирование сводного отчета по сотрудникам.
     * @param string   $searchPhrase
     * @param int|null $offset
     * @param int|null $limit
     * @return stdClass
     */
    public function outgoingEmployeeCalls(string $searchPhrase = '', ?int $offset = null, ?int $limit = null): stdClass
    {
        $tmpSearchPhrase = json_decode($searchPhrase, true);
        $tmpSearchPhrase['typeCall'] = 'outgoing-calls';
        $tmpSearchPhrase['dateRangeSelector'] = self::getDateRanges($tmpSearchPhrase['dateRangeSelector']);
        $minBilSec = $tmpSearchPhrase['minBilSec'] ?? 0;
        $searchPhrase = json_encode($tmpSearchPhrase, JSON_UNESCAPED_SLASHES);
        unset($tmpSearchPhrase);

        $resultView = (object)[
            'data' => [],
            'searchPhrase' => $searchPhrase,
        ];

        $view = $this->history($searchPhrase);
        $baseNumberFilter = $view->baseNumberFilter;
        if (isset($baseNumberFilter['bind'])) {
            $baseNumberFilter = $baseNumberFilter['bind']['filteredExtensions'] ?? [];
        }
        $resultView->additionalFilter = array_unique(
            array_merge($view->additionalFilter['bind']['filteredExtensions'] ?? [], $baseNumberFilter)
        );
        if (empty($resultView->additionalFilter)) {
            $extensionsFilter = [
                'type="' . Extensions::TYPE_SIP . '"',
                'columns' => 'number,callerid',
                'order' => 'number'
            ];
        } else {
            $extensionsFilter = [
                'type="' . Extensions::TYPE_SIP . '" AND number IN ({numbers:array})',
                'columns' => 'number,callerid',
                'order' => 'number',
                'bind' => [
                    'numbers' => $resultView->additionalFilter
                ]
            ];
        }
        $staffNumbers = Extensions::find($extensionsFilter)->toArray();
        $staffNumbers = array_combine(
            array_column($staffNumbers, 'number'),
            array_column($staffNumbers, 'callerid')
        );

        $resultsCdrData = [];
        $typeCallOutgoing = (int)CallHistory::CALL_TYPE_OUTGOING;

        $resultView->srcData = [];
        foreach ($view->data as $call) {
            if ((int)$call['typeCall'] !== $typeCallOutgoing) {
                continue;
            }
            foreach ($call[4] as $detail) {
                if (!empty($resultView->additionalFilter) && !in_array(
                        $detail['src_num'],
                        $resultView->additionalFilter
                    )) {
                    continue;
                }
                if ($detail['billSecInt'] >= $minBilSec) {
                    if (!isset($resultsCdrData[$detail['src_num']])) {
                        $resultsCdrData[$detail['src_num']] = [
                            'count' => 0,
                            'total_billsec' => 0
                        ];
                    }
                    $resultsCdrData[$detail['src_num']]['count']++;
                    $resultsCdrData[$detail['src_num']]['total_billsec'] += $detail['billSecInt'];
                }
            }
        }

        foreach ($staffNumbers as $number => $userData) {
            $staffNumbers[$number] = [
                'number' => $number,
                'callerId' => $userData,
                'countCalls' => $resultsCdrData[$number]['count'] ?? 0,
                'billSecCalls' => $resultsCdrData[$number]['total_billsec'] ?? 0,
                'billMinCalls' => round(($resultsCdrData[$number]['total_billsec'] ?? 0) / 60, 2),
                'billHourCalls' => round(($resultsCdrData[$number]['total_billsec'] ?? 0) / 60 / 60, 2),
            ];
            unset($resultsCdrData[$number]);
        }
        unset($resultsCdrData);

        $resultView->recordsFiltered = count($staffNumbers);

        if ($offset !== null) {
            $staffNumbers = array_slice($staffNumbers, $offset);
        }
        if ($limit !== null) {
            $staffNumbers = array_slice($staffNumbers, 0, $limit);
        }
        $resultView->data = array_values($staffNumbers);
        return $resultView;
    }

    public static function exportOutgoingEmployeeCallsPrintPdf($view, $saveInFile = false): string
    {
        $tmpDir = '/tmp';
        $mpdf = new Mpdf(['tempDir' => $tmpDir]);
        $html = '<h3>Сводная статистика за период: ' . json_decode(
                $view->searchPhrase,
                true
            )['dateRangeSelector'] . '</h3>';
        $html .= '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">';
        $html .= '<tr>';
        $html .= '<thead>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_callerId'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_number'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_billHourCalls'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_billMinCalls'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_billSecCalls'
            ) . '</th>' . '<th>' . Util::translate(
                'repModuleExtendedCDRs_outgoingEmployeeCalls_countCalls'
            ) . '</th>' . '</thead>';
        $html .= '<tbody>';
        foreach ($view->data as $index => $item) {
            $rowStyle = ($index % 2 == 1) ? 'background-color: #f0f0f0;' : '';
            $html .= '<tr>';
            $html .= '<td style="' . $rowStyle . '">' . htmlspecialchars($item['callerId']) . '</td>';
            $html .= '<td style="background-color: #d3d3d3;">' . htmlspecialchars($item['number']) . '</td>';
            $html .= '<td style="' . $rowStyle . '">' . htmlspecialchars($item['billHourCalls']) . '</td>';
            $html .= '<td style="' . $rowStyle . '">' . htmlspecialchars($item['billMinCalls']) . '</td>';
            $html .= '<td style="' . $rowStyle . '">' . htmlspecialchars($item['billSecCalls']) . '</td>';
            $html .= '<td style="' . $rowStyle . '">' . htmlspecialchars($item['countCalls']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html);
        $filename = $tmpDir . '/outgoing-employee-calls-' . time() . '.pdf';
        if ($saveInFile === true) {
            $mpdf->Output($filename, Destination::FILE);
        } else {
            $mpdf->Output('outgoing-employee-calls.pdf', Destination::DOWNLOAD);
        }
        return $filename;
    }

    public static function exportOutgoingEmployeeCallsPrintXls($view): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_callerId'),
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_number'),
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_billHourCalls'),
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_billMinCalls'),
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_billSecCalls'),
            Util::translate('repModuleExtendedCDRs_outgoingEmployeeCalls_countCalls'),
        ];
        $sheet->fromArray($headers, null, 'A1');
        $rowIndex = 2;
        foreach ($view->data as $item) {
            $sheet->setCellValueExplicit('A' . $rowIndex, htmlspecialchars($item['callerId']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $rowIndex, htmlspecialchars($item['number']), DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $rowIndex, htmlspecialchars($item['billHourCalls']));
            $sheet->setCellValue('D' . $rowIndex, htmlspecialchars($item['billMinCalls']));
            $sheet->setCellValue('E' . $rowIndex, htmlspecialchars($item['billSecCalls']));
            $sheet->setCellValue('F' . $rowIndex, htmlspecialchars($item['countCalls']));
            $rowIndex++;
        }
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $maxLength = 0;
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($cellValue !== null) {
                    $maxLength = max($maxLength, strlen($cellValue));
                }
            }
            $sheet->getColumnDimensionByColumn($col)->setWidth($maxLength + 2);
        }
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="outgoing-employee-calls.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    /**
     * Prepares query parameters for filtering CDR records.
     *
     * @param string $searchPhrase The search phrase entered by the user.
     * @param array  $parameters The CDR query parameters.
     * @return array
     */
    private function prepareConditionsForSearchPhrases(string &$searchPhrase, array &$parameters): array
    {
        $searchPhrase = json_decode($searchPhrase, true);
        $dateRangeSelector = $searchPhrase['dateRangeSelector'] ?? '';
        $typeCall = $searchPhrase['typeCall'] ?? '';

        $parameters['conditions'] = '';
        $start = '';
        $end = '';
        // Search date ranges
        if (preg_match_all("/\d{2}\/\d{2}\/\d{4}/", $dateRangeSelector, $matches)) {
            if (count($matches[0]) === 1) {
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][0]);
                $start = $date->format('Y-m-d');
                $end = $date->modify('+1 day')->format('Y-m-d');
                $parameters['conditions'] .= 'start BETWEEN :dateFromPhrase1: AND :dateFromPhrase2:';
                $parameters['bind']['dateFromPhrase1'] = $start;
                $parameters['bind']['dateFromPhrase2'] = $end;
                $searchPhrase = str_replace($matches[0][0], "", $searchPhrase);
            } elseif (count($matches[0]) === 2) {
                $parameters['conditions'] .= 'start BETWEEN :dateFromPhrase1: AND :dateFromPhrase2:';
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][0]);
                $start = $date->format('Y-m-d');
                $parameters['bind']['dateFromPhrase1'] = $start;
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][1]);
                $end = $date->modify('+1 day')->format('Y-m-d');
                $parameters['bind']['dateFromPhrase2'] = $end;
                $searchPhrase = str_replace(
                    [$matches[0][0], $matches[0][1]],
                    '',
                    $searchPhrase
                );
            }
        }
        if (stripos($typeCall, 'incoming') === 0) {
            if ($parameters['conditions'] !== '') {
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_INCOMING;
        } elseif (stripos($typeCall, 'missed') === 0) {
            if ($parameters['conditions'] !== '') {
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_MISSED;
        } elseif (stripos($typeCall, 'outgoing') === 0) {
            if ($parameters['conditions'] !== '') {
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_OUTGOING;
        }

        $additionalFilter = $searchPhrase['additionalFilter'] ?? '';
        // Search phone numbers
        $searchPhrase = str_replace(['(', ')', '-', '+'], '', $searchPhrase);
        $groupNumber = [];
        if (class_exists('\Modules\ModuleUsersGroups\Models\UsersGroups') && preg_match_all(
                '/(?:\s|^)group_(\d+)\b/',
                $additionalFilter,
                $matches
            )) {
            $filter = [
                'group_id IN ({group_id:array})',
                'bind' => [
                    'group_id' => array_unique($matches[1])
                ],
                'columns' => 'user_id'
            ];
            $uIds = array_column(GroupMembers::find($filter)->toArray(), 'user_id');
            if (!empty($uIds)) {
                $filter = [
                    'type=:type: AND userid IN ({userid:array})',
                    'bind' => [
                        'type' => Extensions::TYPE_SIP,
                        'userid' => $uIds,
                    ],
                    'columns' => 'number,userid'

                ];
                $groupNumber = array_column(Extensions::find($filter)->toArray(), 'number');
            }
        }

        $additionalNumbers = [];
        if (preg_match_all('/(?<=\s|^)\d+(?=\s|$)/', $additionalFilter, $matches)) {
            $additionalNumbers = $matches[0];
        }
        $additionalNumbers = array_merge($additionalNumbers, $groupNumber);
        foreach ($additionalNumbers as $index => $value) {
            $additionalNumbers[$index] = ConnectorDB::getPhoneIndex($value);
        }

        $globalNumbers = [];
        $globalSearch = $searchPhrase['globalSearch'] ?? '';
        if (preg_match_all('/(?<=\s|^)\d+(?=\s|$)/', $globalSearch, $matches)) {
            $globalNumbers = $matches[0];
        }
        foreach ($globalNumbers as $index => $value) {
            $globalNumbers[$index] = ConnectorDB::getPhoneIndex($value);
        }

        if (!empty($globalNumbers)) {
            if ($parameters['conditions'] !== '') {
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= '(srcIndex IN ({globalNumbers:array}) OR dstIndex IN ({globalNumbers:array}))';
            $parameters['bind']['globalNumbers'] = array_unique($globalNumbers);
        }

        if (!empty($additionalNumbers)) {
            if ($parameters['conditions'] !== '') {
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= '(srcIndex IN ({additionslNumbers:array}) OR dstIndex IN ({additionslNumbers:array}))';
            $parameters['bind']['additionslNumbers'] = array_unique($additionalNumbers);
        }

        return [$start, $end, $globalNumbers, $additionalNumbers];
    }

    /**
     * Select CDR records with filters based on the provided parameters.
     *
     * @param array $parameters The parameters for filtering CDR records.
     * @return array The selected CDR records.
     */
    private function selectCDRRecordsWithFilters(array $parameters): array
    {
        if (php_sapi_name() !== 'cli') {
            // Apply ACL filters to CDR query using hook method
            PBXConfModulesProvider::hookModulesMethod(CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY, [&$parameters]);
        }
        if(isset($parameters['conditions']) && strpos($parameters['conditions'],  'AND') === 0){
            $parameters['conditions'] = "1=1 ".$parameters['conditions'];
        }
        // Retrieve CDR records based on the filtered parameters
        return ConnectorDB::invoke('getCdr', [$parameters]);
    }

    public function getDateRanges(string $srcPeriod): string
    {
        $dateRanges = [
            'cal_Today' => (new DateTime())->format('d/m/Y') . ' - ' . (new DateTime())->format('d/m/Y'),
            'cal_Yesterday' => (new DateTime())->modify('-1 day')->format('d/m/Y') . ' - ' . (new DateTime())->modify(
                    '-1 day'
                )->format('d/m/Y'),
            'cal_LastWeek' => (new DateTime())->modify('-6 days')->format('d/m/Y') . ' - ' . (new DateTime())->format(
                    'd/m/Y'
                ),
            'cal_Last30Days' => (new DateTime())->modify('-29 days')->format('d/m/Y') . ' - ' . (new DateTime(
                ))->format('d/m/Y'),
            'cal_ThisMonth' => (new DateTime())->modify('first day of this month')->format(
                    'd/m/Y'
                ) . ' - ' . (new DateTime())->modify('last day of this month')->format('d/m/Y'),
            'cal_LastMonth' => (new DateTime())->modify('first day of last month')->format(
                    'd/m/Y'
                ) . ' - ' . (new DateTime())->modify('last day of last month')->format('d/m/Y'),
        ];
        return $dateRanges[$srcPeriod] ?? $srcPeriod;
    }
}