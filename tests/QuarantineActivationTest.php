<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/QuarantineActivation.php';

use Modules\ModuleExtendedCDRs\Lib\QuarantineActivation;

function assertActivationSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertActivationSame(
    ['existing'],
    QuarantineActivation::afterCommit(['existing'], []),
    'uncommitted IDs are not activated'
);
assertActivationSame(
    ['existing', 'new'],
    QuarantineActivation::afterCommit(['existing'], ['new']),
    'committed ID is activated'
);
assertActivationSame(
    ['existing', 'new'],
    QuarantineActivation::afterCommit(['existing', 'new'], ['new']),
    'committed ID is unique'
);

echo "QuarantineActivationTest: OK\n";
