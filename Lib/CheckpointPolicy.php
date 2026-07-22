<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class CheckpointPolicy
{
    /**
     * @param array<string,mixed> $batch
     */
    public static function nextOffset(array $batch): int
    {
        $oldOffset = (int)$batch['oldOffset'];
        if (empty($batch['requestOk']) || empty($batch['saveOk']) || !empty($batch['newQuarantine'])) {
            return $oldOffset;
        }

        $parsedOffset = max($oldOffset, (int)$batch['parsedOffset']);
        $ids = array_values(array_unique(array_map('intval', $batch['rowIds'] ?? [])));
        sort($ids);
        if (empty($ids) || $ids[0] > $oldOffset + 1) {
            return $parsedOffset;
        }

        $set = array_flip($ids);
        $contiguous = $oldOffset;
        while (isset($set[$contiguous + 1])) {
            $contiguous++;
        }

        return min($parsedOffset, $contiguous);
    }
}
