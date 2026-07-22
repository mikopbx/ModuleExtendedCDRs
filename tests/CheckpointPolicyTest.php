<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/CheckpointPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\CheckpointPolicy;

function checkPointExpected(int $expected, array $input, string $message): void
{
    $actual = CheckpointPolicy::nextOffset($input);
    if ($expected !== $actual) {
        throw new RuntimeException("$message: expected $expected, got $actual");
    }
}

$base = ['oldOffset' => 100, 'parsedOffset' => 105, 'requestOk' => true,
    'saveOk' => true, 'newQuarantine' => false, 'rowIds' => [101, 102, 103, 104, 105]];
checkPointExpected(105, $base, 'successful contiguous batch advances');
checkPointExpected(100, array_merge($base, ['requestOk' => false]), 'source error retains offset');
checkPointExpected(100, array_merge($base, ['saveOk' => false]), 'save error retains offset');
checkPointExpected(100, array_merge($base, ['newQuarantine' => true]), 'new quarantine retains offset for replay');
checkPointExpected(102, array_merge($base, ['rowIds' => [101, 102, 104, 105]]), 'gap advances only contiguous prefix');
checkPointExpected(105, array_merge($base, ['rowIds' => [102, 105]]), 'parser offset is authoritative when source groups omit unrelated IDs');

echo "CheckpointPolicyTest: OK\n";
