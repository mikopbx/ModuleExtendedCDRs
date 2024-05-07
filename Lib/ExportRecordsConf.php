<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 12 2019
 */


namespace Modules\ModuleExportRecords\Lib;

use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleExportRecords\Lib\RestAPI\Controllers\ApiController;
use Modules\ModuleExportRecords\bin\ConnectorDB;

class ExportRecordsConf extends ConfigClass
{

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
                $templateMain = new ExportRecordsMain();
                $res          = $templateMain->checkModuleWorkProperly();
                break;
            case 'RELOAD':
                $templateMain = new ExportRecordsMain();
                $templateMain->startAllServices(true);
                $res->success = true;
                break;
            default:
                $res->success    = false;
                $res->messages[] = 'API action not found in moduleRestAPICallback ModuleExportRecords';
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
            [ApiController::class, 'downloads',         '/pbxcore/api/modules/ModuleExportRecords/downloads', 'get', '/', false],
            [ApiController::class, 'downloadsHistory',  '/pbxcore/api/modules/ModuleExportRecords/downloads-history', 'get', '/', false],
            [ApiController::class, 'putTest307',        '/pbxcore/api/modules/ModuleExportRecords/v1/{company}/integrations/{filename}', 'put', '/', false],
            [ApiController::class, 'putTest201',        '/pbxcore/api/modules/ModuleExportRecords/v1/{company}/201/{filename}', 'put', '/', false],
        ];
    }

    /**
     * @param array $tasks
     */
    public function createCronTasks(array &$tasks): void
    {
        $busyboxPath= Util::which('busybox');
        $tasks[]    = "*/1 * * * * $busyboxPath find /storage/usbdisk*/mikopbx/tmp/*/ -mmin +5 -type f -delete> /dev/null 2>&1".PHP_EOL;
    }
}