<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/AtomicBatch.php';

use Modules\ModuleExtendedCDRs\Lib\AtomicBatch;

final class FakeTransactionAdapter
{
    public array $calls = [];
    public bool $beginResult = true;
    public bool $commitResult = true;
    public bool $rollbackResult = true;
    public bool $throwOnRollback = false;

    public function begin(): bool
    {
        $this->calls[] = 'begin';
        return $this->beginResult;
    }

    public function commit(): bool
    {
        $this->calls[] = 'commit';
        return $this->commitResult;
    }

    public function rollback(): bool
    {
        $this->calls[] = 'rollback';
        if ($this->throwOnRollback) {
            throw new RuntimeException('rollback exploded');
        }
        return $this->rollbackResult;
    }
}

function assertAtomicSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$db = new FakeTransactionAdapter();
$success = AtomicBatch::run($db, static fn() => 'saved');
assertAtomicSame(true, $success['ok'], 'successful batch');
assertAtomicSame('saved', $success['value'], 'successful value');
assertAtomicSame(['begin', 'commit'], $db->calls, 'successful transaction calls');

$db = new FakeTransactionAdapter();
$failure = AtomicBatch::run($db, static function (): void {
    throw new RuntimeException('write failed');
});
assertAtomicSame(false, $failure['ok'], 'failed batch');
assertAtomicSame('write failed', $failure['error'], 'failure message');
assertAtomicSame(['begin', 'rollback'], $db->calls, 'failed transaction rolls back');
assertAtomicSame(true, $failure['rollbackOk'], 'successful rollback is reported');

$db = new FakeTransactionAdapter();
$db->beginResult = false;
$beginFailure = AtomicBatch::run($db, static fn() => 'never');
assertAtomicSame(false, $beginFailure['ok'], 'begin failure');
assertAtomicSame(['begin'], $db->calls, 'begin failure does not rollback inactive transaction');

$db = new FakeTransactionAdapter();
$db->commitResult = false;
$commitFailure = AtomicBatch::run($db, static fn() => 'saved');
assertAtomicSame(false, $commitFailure['ok'], 'commit failure');
assertAtomicSame(['begin', 'commit', 'rollback'], $db->calls, 'commit failure attempts rollback');

$db = new FakeTransactionAdapter();
$db->rollbackResult = false;
$rollbackFailure = AtomicBatch::run($db, static function (): void {
    throw new RuntimeException('write failed');
});
assertAtomicSame(false, $rollbackFailure['rollbackOk'], 'false rollback is reported');
assertAtomicSame('transaction_rollback_failed', $rollbackFailure['rollbackError'], 'false rollback category');

$db = new FakeTransactionAdapter();
$db->throwOnRollback = true;
$rollbackException = AtomicBatch::run($db, static function (): void {
    throw new RuntimeException('write failed');
});
assertAtomicSame(false, $rollbackException['rollbackOk'], 'rollback exception is reported');
assertAtomicSame('rollback exploded', $rollbackException['rollbackError'], 'rollback exception message');

echo "AtomicBatchTest: OK\n";
