<?php

namespace Modules\ModuleExtendedCDRs\Lib;

use RuntimeException;
use Throwable;

final class AtomicBatch
{
    /**
     * @param object   $db Phalcon-compatible transaction adapter.
     * @param callable $operation Required database mutations.
     * @return array{ok:bool,value:mixed,error:string}
     */
    public static function run(object $db, callable $operation): array
    {
        $started = false;
        try {
            if ($db->begin() !== true) {
                throw new RuntimeException('transaction_begin_failed');
            }
            $started = true;
            $value = $operation();
            if ($db->commit() !== true) {
                throw new RuntimeException('transaction_commit_failed');
            }
            $started = false;
            return ['ok' => true, 'value' => $value, 'error' => ''];
        } catch (Throwable $e) {
            if ($started) {
                try {
                    $db->rollback();
                } catch (Throwable $ignored) {
                    // Preserve the original failure; rollback diagnostics are emitted by the caller.
                }
            }
            return ['ok' => false, 'value' => null, 'error' => $e->getMessage()];
        }
    }
}
