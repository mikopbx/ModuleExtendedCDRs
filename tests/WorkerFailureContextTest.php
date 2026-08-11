<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerFailureContext.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerFailureContext;

function assertFailureContext($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$metrics = [
    'pid' => 321,
    'uptimeSeconds' => 600,
    'memoryBytes' => 1048576,
    'peakMemoryBytes' => 2097152,
    'openFdCount' => 17,
    'tcpSocketCount' => 4,
    'recordingfile' => '/secret/from-metrics.wav',
];
$error = new RuntimeException('failed /storage/recordings/call.wav linked-secret 79990001122');
$context = WorkerFailureContext::make('beanstalk_wait', $error, $metrics, 1250);

assertFailureContext('worker_dependency_failure', $context['event'], 'stable event');
assertFailureContext('beanstalk_wait', $context['operation'], 'operation');
assertFailureContext(RuntimeException::class, $context['errorClass'], 'exception class');
assertFailureContext('dependency_failure', $context['errorCategory'], 'generic error category');
assertFailureContext(1250, $context['elapsedMs'], 'elapsed milliseconds');
assertFailureContext(321, $context['pid'], 'allowed metric');
assertFailureContext(false, isset($context['message']), 'exception message is excluded');
assertFailureContext(false, isset($context['recordingfile']), 'unknown metric is excluded');
assertFailureContext(false, strpos(json_encode($context), '79990001122') !== false, 'phone number is absent');
assertFailureContext(false, strpos(json_encode($context), 'recordings') !== false, 'path is absent');
assertFailureContext('beanstalk_connect', WorkerFailureContext::invokeOperation(false, true), 'construction operation');
assertFailureContext('beanstalk_request', WorkerFailureContext::invokeOperation(true, true), 'request operation');
assertFailureContext('beanstalk_publish', WorkerFailureContext::invokeOperation(true, false), 'publish operation');

echo "WorkerFailureContextTest: OK\n";
