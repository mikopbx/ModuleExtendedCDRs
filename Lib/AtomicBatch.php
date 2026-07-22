<?php

namespace Modules\ModuleExtendedCDRs\Lib;

use RuntimeException;
use Throwable;

final class AtomicBatch
{
    /**
     * @param object   $db Phalcon-compatible transaction adapter.
     * @param callable $operation Required database mutations.
     * @return array{ok:bool,value:mixed,error:string,rollbackOk:?bool,rollbackError:string}
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
            return [
                'ok' => true,
                'value' => $value,
                'error' => '',
                'rollbackOk' => null,
                'rollbackError' => '',
            ];
        } catch (Throwable $e) {
            $rollbackOk = null;
            $rollbackError = '';
            if ($started) {
                try {
                    $rollbackOk = $db->rollback() === true;
                    if (!$rollbackOk) {
                        $rollbackError = 'transaction_rollback_failed';
                    }
                } catch (Throwable $rollbackException) {
                    $rollbackOk = false;
                    $rollbackError = $rollbackException->getMessage();
                }
            }
            return [
                'ok' => false,
                'value' => null,
                'error' => $e->getMessage(),
                'rollbackOk' => $rollbackOk,
                'rollbackError' => $rollbackError,
            ];
        }
    }
}
