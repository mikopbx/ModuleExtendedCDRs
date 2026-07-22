<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class BatchLogContext
{
    /** @return array<string,mixed> */
    public static function make(array $state): array
    {
        $oldOffset = (int)($state['oldOffset'] ?? 0);
        $proposedOffset = (int)($state['proposedOffset'] ?? $oldOffset);
        $sourceLastId = (int)($state['sourceLastId'] ?? $oldOffset);
        return [
            'event' => 'cdr_sync_batch',
            'oldOffset' => $oldOffset,
            'proposedOffset' => $proposedOffset,
            'sourceLastId' => $sourceLastId,
            'lagBefore' => max(0, $sourceLastId - $oldOffset),
            'lagAfter' => max(0, $sourceLastId - $proposedOffset),
            'minId' => (int)($state['minId'] ?? 0),
            'maxId' => (int)($state['maxId'] ?? 0),
            'linkedIdCount' => (int)($state['linkedIdCount'] ?? 0),
            'rowCount' => (int)($state['rowCount'] ?? 0),
            'inserted' => (int)($state['inserted'] ?? 0),
            'updated' => (int)($state['updated'] ?? 0),
            'mode' => (string)($state['mode'] ?? 'normal'),
            'elapsedMs' => max(0, (int)($state['elapsedMs'] ?? 0)),
            'outcome' => (string)($state['outcome'] ?? 'unknown'),
            'errorCategory' => (string)($state['errorCategory'] ?? ''),
        ];
    }
}
