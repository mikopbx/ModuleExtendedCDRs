<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerRuntimePolicy.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerRuntimePolicy;

function assertRuntimePolicy($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertRuntimePolicy(40, WorkerRuntimePolicy::watchdogDeadlineSeconds(), 'internal watchdog deadline');
assertRuntimePolicy(50, WorkerRuntimePolicy::outerTimeoutSeconds(), 'outer timeout boundary');
assertRuntimePolicy(true, WorkerRuntimePolicy::shouldLogHealth(1000, 0), 'first health event logs');
assertRuntimePolicy(false, WorkerRuntimePolicy::shouldLogHealth(1299, 1000), 'health event is rate limited');
assertRuntimePolicy(true, WorkerRuntimePolicy::shouldLogHealth(1300, 1000), 'health interval boundary logs');

echo "WorkerRuntimePolicyTest: OK\n";
