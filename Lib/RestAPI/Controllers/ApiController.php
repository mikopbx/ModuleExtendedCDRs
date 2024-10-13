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
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once(dirname(__DIR__,3).'/vendor/autoload.php');


class ApiController extends ModulesControllerBase
{

    /**
     * curl -H 'Cookie: PHPSESSID=21d1dbd98258d09e81ab48c36c711006' 'https://boffart.miko.ru/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory?type=json&search=%7B%22dateRangeSelector%22%3A%2212%2F09%2F2024+-+11%2F10%2F2024%22%2C%22globalSearch%22%3A%22%22%2C%22typeCall%22%3A%22outgoing-calls%22%2C%22additionalFilter%22%3A%22%22%7D'
     * @return void
     */
    public function exportHistory()
    {
        $type           = $this->request->get('type');
        $searchPhrase   = $this->request->get('search');
        $gr = new GetReport();
        $view = $gr->history($searchPhrase);
        if($type === 'json'){
            $this->echoResponse((array)$view);
        }elseif($type === 'pdf'){
            $this->printPdf($view);
        }elseif ($type === 'xlsx'){
            $this->printXls($view);
        }
        $this->response->sendRaw();
    }
    private function printXls($view):void
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
        $sheet->fromArray($headers, NULL, 'A1');
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

    private function printPdf($view):void
    {
        $mpdf = new Mpdf([
                             'tempDir' => '/tmp',
                         ]);
        $html  = '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">';
        $html .= '<tr>';

        $html .= '<thead>'.
                 '<th>' . Util::translate('repModuleExtendedCDRs_cdr_ColumnTypeState') . '</th>'.
                 '<th>' . Util::translate('cdr_ColumnDate') . '</th>'.
                 '<th>' . Util::translate('cdr_ColumnFrom') . '</th>'.
                 '<th>' . Util::translate('cdr_ColumnTo') . '</th>'.
                 '<th>' . Util::translate('repModuleExtendedCDRs_cdr_ColumnLine') . '</th>'.
                 '<th>' . Util::translate('repModuleExtendedCDRs_cdr_ColumnWaitTime') . '</th>'.
                 '<th>' . Util::translate('cdr_ColumnDuration') . '</th>'.
                 '<th>' . Util::translate('repModuleExtendedCDRs_cdr_ColumnCallState') . '</th>'.
                 '<th>id</th>'.
                 '</thead>';
        $html .= '<tbody>';

        foreach ($view->data as $baseItem) {
            foreach ($baseItem['4'] as $item){
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
        $mpdf->Output('calls_report.pdf', Destination::DOWNLOAD);
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