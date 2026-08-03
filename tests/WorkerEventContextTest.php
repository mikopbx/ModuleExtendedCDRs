<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerEventContext.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerEventContext;

function assertWorkerEvent($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$request = [
    'action' => 'invoke',
    'function' => 'getRecordingPathByID',
    'args' => ['79990001122', '/storage/recordings/secret.wav'],
    'need-ret' => true,
    'linkedid' => 'secret-linked-id',
];
$context = WorkerEventContext::make($request, 'completed');

assertWorkerEvent('worker_event', $context['event'], 'stable event');
assertWorkerEvent('invoke', $context['action'], 'action');
assertWorkerEvent('getRecordingPathByID', $context['function'], 'function');
assertWorkerEvent(true, $context['needsReply'], 'reply flag');
assertWorkerEvent('completed', $context['outcome'], 'outcome');
$encoded = json_encode($context);
assertWorkerEvent(false, strpos($encoded, '79990001122') !== false, 'arguments are excluded');
assertWorkerEvent(false, strpos($encoded, 'recordings') !== false, 'paths are excluded');
assertWorkerEvent(false, strpos($encoded, 'linked-id') !== false, 'linked IDs are excluded');

echo "WorkerEventContextTest: OK\n";
