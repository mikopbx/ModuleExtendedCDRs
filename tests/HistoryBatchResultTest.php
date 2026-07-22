<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/HistoryBatchResult.php';

use Modules\ModuleExtendedCDRs\Lib\HistoryBatchResult;

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$empty = HistoryBatchResult::make(42, [], true, 100);
expectSame(true, $empty['ok'], 'successful empty result is successful');
expectSame(42, $empty['newOffset'], 'empty result keeps offset');
expectSame(0, $empty['rowCount'], 'empty result has no rows');
expectSame('', $empty['error'], 'empty result has no error');

$failed = HistoryBatchResult::make(42, [], false, 100, 'source_timeout');
expectSame(false, $failed['ok'], 'failed request is not empty success');
expectSame('source_timeout', $failed['error'], 'failed request exposes category');

$data = [
    'call-a' => ['rows' => [['id' => 44], ['id' => 47]]],
    'call-b' => ['rows' => [['id' => 51]]],
];
$batch = HistoryBatchResult::make(42, $data, true, 2);
expectSame(44, $batch['minId'], 'minimum row id');
expectSame(51, $batch['maxId'], 'maximum row id');
expectSame(3, $batch['rowCount'], 'total row count');
expectSame(2, $batch['linkedIdCount'], 'linked id count');
expectSame(true, $batch['limitReached'], 'linked id limit signal');

echo "HistoryBatchResultTest: OK\n";
