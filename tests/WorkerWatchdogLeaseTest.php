<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerWatchdogLease.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerWatchdogLease;

function assertLease($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$path = tempnam(sys_get_temp_dir(), 'extended-cdr-watchdog-');
if ($path === false) {
    throw new RuntimeException('Unable to create lease fixture');
}

file_put_contents($path, '{"pid":999,"startedAt":1}');

try {
    $first = WorkerWatchdogLease::tryAcquire($path, 101, 1700000000);
    assertLease(true, $first instanceof WorkerWatchdogLease, 'stale contents do not block first owner');
    assertLease(
        ['pid' => 101, 'startedAt' => 1700000000],
        json_decode((string) file_get_contents($path), true),
        'owner diagnostics are recorded'
    );

    $second = WorkerWatchdogLease::tryAcquire($path, 202, 1700000001);
    assertLease(null, $second, 'contender skips while first owner holds lock');

    $first->release();
    $third = WorkerWatchdogLease::tryAcquire($path, 303, 1700000002);
    assertLease(true, $third instanceof WorkerWatchdogLease, 'released lease can be reacquired');
    $third->release();
    $third->release();
} finally {
    @unlink($path);
}

echo "WorkerWatchdogLeaseTest: OK\n";
