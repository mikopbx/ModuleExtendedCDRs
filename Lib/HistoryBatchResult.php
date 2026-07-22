<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class HistoryBatchResult
{
    /**
     * @param array<string,array> $data
     * @return array<string,mixed>
     */
    public static function make(
        int $oldOffset,
        array $data,
        bool $ok,
        int $linkedIdLimit,
        string $error = '',
        ?int $newOffset = null
    ): array {
        $ids = [];
        foreach ($data as $call) {
            foreach ($call['rows'] ?? [] as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return [
            'ok' => $ok,
            'data' => $data,
            'oldOffset' => $oldOffset,
            'newOffset' => $newOffset ?? $oldOffset,
            'minId' => empty($ids) ? 0 : min($ids),
            'maxId' => empty($ids) ? 0 : max($ids),
            'rowCount' => count($ids),
            'linkedIdCount' => count($data),
            'limitReached' => count($data) >= $linkedIdLimit,
            'error' => $ok ? '' : ($error ?: 'source_request_failed'),
        ];
    }
}
