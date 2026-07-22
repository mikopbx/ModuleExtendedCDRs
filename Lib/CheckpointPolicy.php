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

        // SelectCDR intentionally filters and groups source rows, so gaps in raw IDs
        // are normal. The parser checkpoint is the authoritative safe boundary.
        return max($oldOffset, (int)$batch['parsedOffset']);
    }
}
