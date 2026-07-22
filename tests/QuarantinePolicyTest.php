<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/QuarantinePolicy.php';

use Modules\ModuleExtendedCDRs\Lib\QuarantinePolicy;

function quarantineAssert($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("$message: expected " . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$first = QuarantinePolicy::failed([], 'row_limit', 1000);
quarantineAssert(1, $first['attempts'], 'first failure attempt');
quarantineAssert(1000, $first['firstFailureAt'], 'first failure timestamp');
quarantineAssert('pending', $first['status'], 'first failure pending');

$second = QuarantinePolicy::failed($first, 'row_limit', 1100);
quarantineAssert(2, $second['attempts'], 'attempt increments');
quarantineAssert(true, $second['nextRetryAt'] > $first['nextRetryAt'], 'backoff grows');

$state = $second;
for ($attempt = 2; $attempt < QuarantinePolicy::MAX_ATTEMPTS; $attempt++) {
    $state = QuarantinePolicy::failed($state, 'row_limit', 1100 + $attempt);
}
quarantineAssert('manual', $state['status'], 'maximum attempts require manual review');

$resolved = QuarantinePolicy::resolved($second, 2000);
quarantineAssert('resolved', $resolved['status'], 'successful retry resolves');
quarantineAssert(2000, $resolved['lastFailureAt'], 'resolution timestamp retained for audit');

$manual = QuarantinePolicy::manual('row_limit', 3000);
quarantineAssert('manual', $manual['status'], 'oversized call requires manual resolution');
quarantineAssert(0, $manual['nextRetryAt'], 'manual quarantine has no fake retry deadline');

echo "QuarantinePolicyTest: OK\n";
