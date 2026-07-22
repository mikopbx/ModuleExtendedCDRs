<?php

namespace Modules\ModuleExtendedCDRs\Lib;

/**
 * Pure policy for selecting the CDR synchronization mode and pacing.
 */
final class SyncPolicy
{
    public const MODE_NORMAL = 'normal';
    public const MODE_CATCH_UP = 'catch_up';
    public const MODE_ERROR = 'error';

    public const CATCH_UP_ENTER_LAG = 5000;
    public const CATCH_UP_EXIT_LAG = 500;
    public const NORMAL_BATCH_LINKED_IDS = 100;
    public const CATCH_UP_BATCH_LINKED_IDS = 500;
    public const MAX_BATCH_LINKED_IDS = 1000;
    public const NORMAL_DELAY_SECONDS = 10;
    public const ERROR_DELAY_SECONDS = 30;

    /**
     * @return array{lag:int,mode:string,delay:int,batchLinkedIds:int}
     */
    public static function decide(
        int $offset,
        int $sourceLastId,
        bool $requestOk,
        bool $limitReached,
        bool $wasCatchUp
    ): array {
        $lag = max(0, $sourceLastId - $offset);

        if (!$requestOk) {
            return [
                'lag' => $lag,
                'mode' => self::MODE_ERROR,
                'delay' => self::ERROR_DELAY_SECONDS,
                'batchLinkedIds' => self::NORMAL_BATCH_LINKED_IDS,
            ];
        }

        $catchUp = $wasCatchUp
            ? $lag > self::CATCH_UP_EXIT_LAG
            : $lag >= self::CATCH_UP_ENTER_LAG;

        $batchLinkedIds = $catchUp
            ? self::CATCH_UP_BATCH_LINKED_IDS
            : self::NORMAL_BATCH_LINKED_IDS;
        if ($limitReached) {
            $batchLinkedIds = self::MAX_BATCH_LINKED_IDS;
        }

        return [
            'lag' => $lag,
            'mode' => $catchUp ? self::MODE_CATCH_UP : self::MODE_NORMAL,
            'delay' => $catchUp ? 0 : self::NORMAL_DELAY_SECONDS,
            'batchLinkedIds' => min(self::MAX_BATCH_LINKED_IDS, $batchLinkedIds),
        ];
    }
}
