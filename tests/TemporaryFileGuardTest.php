<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/TemporaryFileGuard.php';

use Modules\ModuleExtendedCDRs\Lib\TemporaryFileGuard;

function assertTemporaryGuard($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$owned = tempnam(sys_get_temp_dir(), 'extended-cdr-owned-');
$transferred = tempnam(sys_get_temp_dir(), 'extended-cdr-transferred-');
if ($owned === false || $transferred === false) {
    throw new RuntimeException('Unable to create temporary fixtures');
}

$guard = new TemporaryFileGuard();
$guard->track($owned);
$guard->track($transferred);
$guard->forget($transferred);
$guard->cleanup();
$guard->cleanup();

assertTemporaryGuard(false, file_exists($owned), 'owned file is removed');
assertTemporaryGuard(true, file_exists($transferred), 'transferred file is preserved');
unlink($transferred);

echo "TemporaryFileGuardTest: OK\n";
