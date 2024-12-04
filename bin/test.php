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
use Modules\ModuleExtendedCDRs\Models\ReportSettings;

require_once('Globals.php');


$variants = ReportSettings::find()->toArray();
var_dump($variants);
exit();

$searchPhrase = "{\"dateRangeSelector\":\"12\/09\/2024 - 11\/10\/2024\",\"globalSearch\":\"\",\"typeCall\":\"outgoing-calls\",\"additionalFilter\":\"\"}";
$searchPhrase = "{\"dateRangeSelector\":\"01/10/2024 - 31/10/2024\",\"globalSearch\":\"\",\"typeCall\":\"all-calls\",\"additionalFilter\":\"201\"}";
$gr = new GetReport();
$view = $gr->outgoingEmployeeCalls($searchPhrase);

print_r(array_values($view->data));
exit();