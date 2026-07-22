<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/BatchLogContext.php';

use Modules\ModuleExtendedCDRs\Lib\BatchLogContext;

function assertLogValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$context = BatchLogContext::make([
    'oldOffset' => 100,
    'proposedOffset' => 120,
    'sourceLastId' => 150,
    'minId' => 101,
    'maxId' => 120,
    'linkedIdCount' => 3,
    'rowCount' => 20,
    'inserted' => 18,
    'updated' => 2,
    'mode' => 'normal',
    'elapsedMs' => 42,
    'outcome' => 'committed',
    'errorCategory' => '',
    'linkedid' => 'secret-call-id',
    'UNIQUEID' => 'secret-unique-id',
    'src_num' => '79990001122',
    'recordingfile' => '/secret.wav',
]);

assertLogValue('cdr_sync_batch', $context['event'], 'stable event name');
assertLogValue(50, $context['lagBefore'], 'lag before batch');
assertLogValue(30, $context['lagAfter'], 'lag after batch');
foreach (['linkedid', 'UNIQUEID', 'src_num', 'recordingfile'] as $forbidden) {
    assertLogValue(false, array_key_exists($forbidden, $context), "forbidden key $forbidden");
}

echo "BatchLogContextTest: OK\n";
