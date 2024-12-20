<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2022 Alexey Portnov and Nikolay Beketov
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
use MikoPBX\Core\System\System;
use MikoPBX\Core\System\Util;
use Phalcon\Logger\Adapter\Stream;
use Cesargb\Log\Rotation;
use Cesargb\Log\Exceptions\RotationFailed;
use Phalcon\Logger\Formatter\Line as LineFormatter;

require_once('Globals.php');
require_once(dirname(__DIR__).'/vendor/autoload.php');

class Logger
{
    public bool $debug;
    private $logger;
    private string $module_name;
    private string $logFile;

    /**
     * Logger constructor.
     *
     * @param string $class
     * @param string $module_name
     */
    public function __construct(string $class, string $module_name)
    {
        $this->module_name = $module_name;
        $this->debug    = true;
        $logPath        = System::getLogDir() . '/' . $this->module_name . '/';
        if (!is_dir($logPath)){
            Util::mwMkdir($logPath);
            Util::addRegularWWWRights($logPath);
        }
        $this->logFile  = $logPath . $class . '.log';
        $adapter       = new Stream($this->logFile);
        $lineFormatter = new LineFormatter("[%date%] %message%", "Y-m-d H:i:s");
        $adapter->setFormatter($lineFormatter);
        $loggerClass = MikoPBXVersion::getLoggerClass();
        $this->logger  = new $loggerClass(
            'messages',
            [
                'main' => $adapter,
            ]
        );
    }

    public function rotate(): void
    {
        $rotation = new Rotation([
             'files' => 5,
             'compress' => false,
             'min-size' => 10*1024*1024,
             'truncate' => false,
             'catch' => function (RotationFailed $exception) {
                Util::sysLogMsg('ModuleExport-Log', $exception->getMessage());
             },
        ]);
        $rotation->rotate($this->logFile);
    }

    public function writeError($data): void
    {
        if ($this->debug) {
            $this->logger->error($this->getDecodedString($data));
        }
    }

    public function writeInfo($data): void
    {
        if ($this->debug) {
            $this->logger->info($this->getDecodedString($data));
        }
    }

    public function info(string $data): void
    {
        $this->logger->info($data);
    }

    private function getDecodedString($data):string
    {
        $printedData = json_encode($data, JSON_THROW_ON_ERROR);
        if(is_bool($printedData)){
            $result = '';
        }else{
            $result = urldecode($printedData);
        }
        return $result;
    }

}