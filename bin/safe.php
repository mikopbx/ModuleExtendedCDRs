<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2025 Alexey Portnov and Nikolay Beketov
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

use MikoPBX\Core\System\Util;
use MikoPBX\Core\System\Processes;
use MikoPBX\Core\System\SystemMessages;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleExtendedCDRs\Lib\ExtendedCDRsConf;
use Modules\ModuleExtendedCDRs\Lib\WorkerRuntimePolicy;
use Modules\ModuleExtendedCDRs\Lib\WorkerLogRateLimiter;
use Modules\ModuleExtendedCDRs\Lib\WorkerWatchdogLease;
use Modules\ModuleExtendedCDRs\Lib\WorkerWatchdogRunner;
require_once 'Globals.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$moduleEnable = PbxExtensionUtils::isEnabled('ModuleExtendedCDRs');
if(!$moduleEnable){
    exit(1);
}

$startedAt = time();
$lockPath = '/var/run/ModuleExtendedCDRs/watchdog.lock';
$skipLogPath = '/var/run/ModuleExtendedCDRs/watchdog-skip.log';
$lease = null;
$activePhase = 'acquire_lock';

try {
    $lease = WorkerWatchdogLease::tryAcquire($lockPath, getmypid(), $startedAt);
    if ($lease === null) {
        if (WorkerLogRateLimiter::shouldLog($skipLogPath, time(), 300)) {
            SystemMessages::sysLogMsg(
                'ModuleExtendedCDRs_SAFE',
                json_encode(['event' => 'worker_watchdog_skipped', 'reason' => 'lock_busy']),
                LOG_NOTICE
            );
        }
        exit(0);
    }

    if (function_exists('pcntl_async_signals') && function_exists('pcntl_alarm')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use (&$activePhase, $startedAt): void {
            SystemMessages::sysLogMsg(
                'ModuleExtendedCDRs_SAFE',
                json_encode([
                    'event' => 'worker_watchdog_timeout',
                    'phase' => $activePhase,
                    'elapsedMs' => (time() - $startedAt) * 1000,
                ]),
                LOG_ERR
            );
            exit(124);
        });
        pcntl_alarm(WorkerRuntimePolicy::watchdogDeadlineSeconds());
    }

    $activePhase = 'load_workers';
    $conf = new ExtendedCDRsConf();
    $workers = array_column($conf->getModuleWorkers(), 'worker');
    $busyboxPath = Util::which('busybox');

    $status = WorkerWatchdogRunner::run(
        $workers,
        static function (string $worker) use (&$activePhase): string {
            $activePhase = 'find_worker';
            return (string) Processes::getPidOfProcess($worker);
        },
        static function (string $worker) use (&$activePhase): void {
            $activePhase = 'start_worker';
            Processes::processPHPWorker($worker);
        },
        static function (array $duplicates) use (&$activePhase, $busyboxPath): void {
            $activePhase = 'signal_duplicates';
            $pids = implode(' ', array_map('intval', $duplicates));
            shell_exec(escapeshellarg($busyboxPath) . ' kill -SIGUSR2 ' . $pids);
        },
        static function (array $event): void {
            SystemMessages::sysLogMsg('ModuleExtendedCDRs_SAFE', json_encode($event), LOG_NOTICE);
        }
    );
} catch (Throwable $error) {
    SystemMessages::sysLogMsg(
        'ModuleExtendedCDRs_SAFE',
        json_encode([
            'event' => 'worker_watchdog_failed',
            'phase' => $activePhase,
            'errorClass' => get_class($error),
            'elapsedMs' => (time() - $startedAt) * 1000,
        ]),
        LOG_ERR
    );
    $status = 1;
} finally {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    if ($lease instanceof WorkerWatchdogLease) {
        $lease->release();
    }
}

exit($status ?? 1);
