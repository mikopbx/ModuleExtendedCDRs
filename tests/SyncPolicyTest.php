<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/SyncPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\SyncPolicy;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$current = SyncPolicy::decide(1000, 1000, true, false, false);
assertSameValue(0, $current['lag'], 'zero lag');
assertSameValue(SyncPolicy::MODE_NORMAL, $current['mode'], 'zero lag uses normal mode');
assertSameValue(SyncPolicy::NORMAL_DELAY_SECONDS, $current['delay'], 'normal mode delay');

$normal = SyncPolicy::decide(1000, 1200, true, false, false);
assertSameValue(SyncPolicy::MODE_NORMAL, $normal['mode'], 'small lag remains normal');
assertSameValue(SyncPolicy::NORMAL_BATCH_LINKED_IDS, $normal['batchLinkedIds'], 'normal batch size');

$catchUp = SyncPolicy::decide(1000, 7000, true, false, false);
assertSameValue(SyncPolicy::MODE_CATCH_UP, $catchUp['mode'], 'large lag enters catch-up');
assertSameValue(0, $catchUp['delay'], 'catch-up has no fixed delay');
assertSameValue(SyncPolicy::CATCH_UP_BATCH_LINKED_IDS, $catchUp['batchLinkedIds'], 'catch-up batch size');

$hysteresis = SyncPolicy::decide(1000, 1800, true, false, true);
assertSameValue(SyncPolicy::MODE_CATCH_UP, $hysteresis['mode'], 'catch-up remains active above exit threshold');

$caughtUp = SyncPolicy::decide(1000, 1100, true, false, true);
assertSameValue(SyncPolicy::MODE_NORMAL, $caughtUp['mode'], 'catch-up exits below exit threshold');

$failed = SyncPolicy::decide(1000, 9000, false, false, true);
assertSameValue(SyncPolicy::MODE_ERROR, $failed['mode'], 'request failure uses error mode');
assertSameValue(SyncPolicy::ERROR_DELAY_SECONDS, $failed['delay'], 'request failure backs off');

$limited = SyncPolicy::decide(1000, 7000, true, true, false);
assertSameValue(SyncPolicy::MAX_BATCH_LINKED_IDS, $limited['batchLinkedIds'], 'limit pressure uses capped batch');

echo "SyncPolicyTest: OK\n";
