<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerLogRateLimiter.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerLogRateLimiter;

function assertLogLimiter($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$path = sys_get_temp_dir() . '/extended-cdr-log-limit-' . bin2hex(random_bytes(6));
try {
    assertLogLimiter(true, WorkerLogRateLimiter::shouldLog($path, 1000, 300), 'first event logs');
    assertLogLimiter(false, WorkerLogRateLimiter::shouldLog($path, 1299, 300), 'event inside interval skips');
    assertLogLimiter(true, WorkerLogRateLimiter::shouldLog($path, 1300, 300), 'interval boundary logs');
} finally {
    @unlink($path);
}

echo "WorkerLogRateLimiterTest: OK\n";
