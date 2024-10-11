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

use Modules\ModuleExtendedCDRs\Lib\GetReport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once('Globals.php');

require_once(dirname(__DIR__).'/vendor/autoload.php');


$searchPhrase = "{\"dateRangeSelector\":\"12\/09\/2024 - 11\/10\/2024\",\"globalSearch\":\"\",\"typeCall\":\"outgoing-calls\",\"additionalFilter\":\"\"}";
$gr = new GetReport();
$view = $gr->history($searchPhrase);
print_r($view);

exit();
// Создаем экземпляр mPDF
$mpdf = new \Mpdf\Mpdf();

// Пример ассоциативного массива
$data = [
    ['name' => 'Иван', 'age' => 25, 'city' => 'Москва'],
    ['name' => 'Мария', 'age' => 30, 'city' => 'Санкт-Петербург'],
    ['name' => 'Петр', 'age' => 28, 'city' => 'Новосибирск'],
    ['name' => 'Ольга', 'age' => 22, 'city' => 'Екатеринбург']
];

// Генерация HTML для вывода в PDF
$html = '<h1>Список пользователей</h1>';
$html .= '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">';
$html .= '<thead><tr><th>Имя</th><th>Возраст</th><th>Город</th></tr></thead>';
$html .= '<tbody>';

foreach ($data as $row) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($row['name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($row['age']) . '</td>';
    $html .= '<td>' . htmlspecialchars($row['city']) . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

// Загружаем HTML в mPDF
$mpdf->WriteHTML($html);

// Выводим PDF напрямую в браузер
$mpdf->Output('/root/users.pdf', \Mpdf\Output\Destination::FILE);



// Создаем новый экземпляр Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Пример ассоциативного массива
$data = [
    ['name' => 'Иван', 'age' => 25, 'city' => 'Москва'],
    ['name' => 'Мария', 'age' => 30, 'city' => 'Санкт-Петербург'],
    ['name' => 'Петр', 'age' => 28, 'city' => 'Новосибирск'],
    ['name' => 'Ольга', 'age' => 22, 'city' => 'Екатеринбург']
];

// Устанавливаем заголовки столбцов
$sheet->setCellValue('A1', 'Имя');
$sheet->setCellValue('B1', 'Возраст');
$sheet->setCellValue('C1', 'Город');

// Добавляем данные в ячейки
$row = 2; // Начинаем со второй строки, так как первая — заголовки
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item['name']);
    $sheet->setCellValue('B' . $row, $item['age']);
    $sheet->setCellValue('C' . $row, $item['city']);
    $row++;
}

// Создаем объект для записи файла
$writer = new Xlsx($spreadsheet);

// Сохраняем Excel-файл на сервере
$writer->save('/root/users.xlsx');