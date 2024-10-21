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

use MikoPBX\Core\System\SystemMessages;
use Modules\ModuleExtendedCDRs\Lib\GetReport;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;
use MikoPBX\Core\System\Notifications;
use MikoPBX\Core\System\Util;
require_once('Globals.php');

$id = $argv[1]??null;
$ident = 'report2email';
SystemMessages::sysLogMsg($ident, "starting $ident, id: $id");
if($id === null){
    SystemMessages::sysLogMsg($ident, "id is null");
    exit(1);
}
$settings = ReportSettings::findFirst(["id=:id:", 'bind' => ['id' => $id]]);
if(!$settings || (int)$settings->sendingScheduledReport !== 1 || empty($settings->email)){
    SystemMessages::sysLogMsg($ident, "sendingScheduledReport is not 1 OR empty email");
    exit(2);
}
if('CallDetails' === $settings->reportNameID){
    $gr = new GetReport();
    $view = $gr->history($settings->searchText);
    $filename = GetReport::exportHistoryPdf($view, true);
}elseif ('OutgoingEmployeeCalls' === $settings->reportNameID){
    $gr = new GetReport();
    $view = $gr->outgoingEmployeeCalls($settings->searchText);
    $filename = GetReport::exportOutgoingEmployeeCallsPrintPdf($view, true);
}else{
    SystemMessages::sysLogMsg($ident, "unknow reportNameID: $settings->reportNameID");
    exit(0);
}
$notifications = new Notifications();
$subject = empty($settings->variantName)?Util::translate("repModuleExtendedCDRs_$settings->reportNameID"):$settings->variantName;
$emails = implode(',',  explode(' ', $settings->email));

if($notifications->sendMail($emails, $subject, '<hr>', $filename)){
    SystemMessages::sysLogMsg($ident, "OK, email send to $emails");
}else{
    SystemMessages::sysLogMsg($ident, "FAIL, email NOT send to $emails");
}
unlink($filename);