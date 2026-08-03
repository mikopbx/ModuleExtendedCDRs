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

$directory = sys_get_temp_dir() . '/extended-cdr-watchdog-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$path = $directory . '/watchdog.lock';

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

    $target = $directory . '/target';
    $link = $directory . '/symlink.lock';
    file_put_contents($target, 'must-not-change');
    symlink($target, $link);
    try {
        WorkerWatchdogLease::tryAcquire($link, 404, 1700000003);
        throw new RuntimeException('symlink lock path must be rejected');
    } catch (RuntimeException $error) {
        assertLease('must-not-change', file_get_contents($target), 'symlink target remains unchanged');
    }
} finally {
    @unlink($path);
    @unlink($link ?? '');
    @unlink($target ?? '');
    @rmdir($directory);
}

echo "WorkerWatchdogLeaseTest: OK\n";
