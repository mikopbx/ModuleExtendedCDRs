<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerProcessMetrics.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerProcessMetrics;

function assertProcessMetric($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$root = sys_get_temp_dir() . '/extended-cdr-proc-' . bin2hex(random_bytes(6));
$pid = 4321;
mkdir($root . '/' . $pid . '/fd', 0775, true);
symlink('/tmp/plain-file', $root . '/' . $pid . '/fd/0');
symlink('socket:[12345]', $root . '/' . $pid . '/fd/1');
symlink('socket:[67890]', $root . '/' . $pid . '/fd/2');

try {
    $metrics = WorkerProcessMetrics::collect($pid, time() - 12, $root);
    assertProcessMetric($pid, $metrics['pid'], 'pid');
    assertProcessMetric(true, $metrics['uptimeSeconds'] >= 12, 'uptime is non-negative');
    assertProcessMetric(true, $metrics['memoryBytes'] > 0, 'memory is available');
    assertProcessMetric(true, $metrics['peakMemoryBytes'] > 0, 'peak memory is available');
    assertProcessMetric(3, $metrics['openFdCount'], 'open descriptor count');
    assertProcessMetric(2, $metrics['tcpSocketCount'], 'socket descriptor count');

    $missing = WorkerProcessMetrics::collect($pid, time(), $root . '/missing');
    assertProcessMetric(null, $missing['openFdCount'], 'missing proc descriptor count');
    assertProcessMetric(null, $missing['tcpSocketCount'], 'missing proc socket count');
} finally {
    foreach (glob($root . '/' . $pid . '/fd/*') ?: [] as $entry) {
        unlink($entry);
    }
    rmdir($root . '/' . $pid . '/fd');
    rmdir($root . '/' . $pid);
    rmdir($root);
}

echo "WorkerProcessMetricsTest: OK\n";
