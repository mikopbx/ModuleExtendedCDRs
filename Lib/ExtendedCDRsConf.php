<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 12 2019
 */


namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\Core\System\Configs\CronConf;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleAmoCrm\Models\ModuleAmoCrm;
use Modules\ModuleExtendedCDRs\Lib\RestAPI\Controllers\ApiController;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Models\ReportSettings;

class ExtendedCDRsConf extends ConfigClass
{

    /**
     * Receive information about mikopbx main database changes
     *
     * @param $data
     */
    public function modelsEventChangeData($data): void
    {
        if ($data['model'] === ReportSettings::class) {
            $cron = new CronConf();
            $cron->reStart();
        }
    }
    /**
     * Returns module workers to start it at WorkerSafeScriptCore
     *
     * @return array
     */
    public function getModuleWorkers(): array
    {
        return [
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_BEANSTALK,
                'worker' => ConnectorDB::class,
            ],
        ];
    }
    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $res    = new PBXApiResult();
        $res->processor = __METHOD__;
        $action = strtoupper($request['action']);
        switch ($action) {
            case 'CHECK':
                $templateMain = new ExtendedCDRsMain();
                $res          = $templateMain->checkModuleWorkProperly();
                break;
            case 'RELOAD':
                $templateMain = new ExtendedCDRsMain();
                $templateMain->startAllServices(true);
                $res->success = true;
                break;
            default:
                $res->success    = false;
                $res->messages[] = 'API action not found in moduleRestAPICallback ModuleExtendedCDRs';
        }

        return $res;
    }

    /**
     * REST API модуля.
     * @return array[]
     */
    public function getPBXCoreRESTAdditionalRoutes(): array
    {
        return [
            [ApiController::class, 'downloads',                     '/pbxcore/api/modules/ModuleExtendedCDRs/downloads', 'get', '/', false],
            [ApiController::class, 'exportHistory',                 '/pbxcore/api/modules/ModuleExtendedCDRs/exportHistory', 'get', '/', false],
            [ApiController::class, 'exportOutgoingEmployeeCalls',   '/pbxcore/api/modules/ModuleExtendedCDRs/exportOutgoingEmployeeCalls', 'get', '/', false],
        ];
    }

    /**
     * @param array $tasks
     */
    public function createCronTasks(array &$tasks): void
    {
        $busyboxPath= Util::which('busybox');
        $tasks[]    = "*/1 * * * * $busyboxPath find /storage/usbdisk*/mikopbx/tmp/ModuleExtendedCDRs/ -mmin +5 -type f -delete> /dev/null 2>&1".PHP_EOL;
        $phpPath    = Util::which('php');

        $reportsData = ReportSettings::find('sendingScheduledReport=1');
        foreach ($reportsData as $settings) {
            if(empty($settings->time)){
                $h = '*';
                $m = '*';
            }else{
                [$h,$m] = explode(':',$settings->time);
                $h = (int)$h;
                $m = (int)$m;
            }
            $dateMonth = empty($settings->dateMonth)?'*':$settings->dateMonth;
            $dayWeek   = empty($settings->day)?'*':$settings->day;

            $tasks[]    = "$m $h $dateMonth * $dayWeek $phpPath $this->moduleDir/bin/report2email.php '$settings->id' > /dev/null 2>&1".PHP_EOL;
        }
    }
}