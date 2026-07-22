<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/BatchPersistenceResult.php';

use Modules\ModuleExtendedCDRs\Lib\BatchPersistenceResult;

function assertPersistenceSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$success = BatchPersistenceResult::success(12, 4);
assertPersistenceSame(true, $success['ok'], 'success status');
assertPersistenceSame(12, $success['inserted'], 'insert count');
assertPersistenceSame(4, $success['updated'], 'update count');
assertPersistenceSame('', $success['errorCategory'], 'success category');

$failure = BatchPersistenceResult::failure('insert_failed', 'database is locked');
assertPersistenceSame(false, $failure['ok'], 'failure status');
assertPersistenceSame(0, $failure['inserted'], 'failure insert count');
assertPersistenceSame(0, $failure['updated'], 'failure update count');
assertPersistenceSame('insert_failed', $failure['errorCategory'], 'failure category');
assertPersistenceSame('database is locked', $failure['message'], 'failure message');

echo "BatchPersistenceResultTest: OK\n";
