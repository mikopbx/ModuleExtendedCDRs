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

namespace Modules\ModuleExportRecords\Lib\Providers;

use MikoPBX\Common\Providers\DatabaseProviderBase;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;

class CdrDbProvider extends DatabaseProviderBase implements ServiceProviderInterface
{

    public const SERVICE_NAME = 'm_ExportRecordsCDR';

    /**
     * Register dbCDR service provider
     *
     * @param DiInterface $di The DI container.
     */
    public function register(DiInterface $di): void
    {
        $dir = dirname(__DIR__, 2);

        $dbConfig = [
            'debugMode' => false,
            'adapter' => 'Sqlite',
            'dbfile' => $dir.'/db/cdr.db',
            'debugLogFile' => $dir.'/db/cdr.db.log'
        ];

        $this->registerDBService(self::SERVICE_NAME, $di, $dbConfig);
    }

}