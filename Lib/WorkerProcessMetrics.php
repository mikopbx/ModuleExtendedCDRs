<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class WorkerProcessMetrics
{
    /**
     * @return array<string, int|null>
     */
    public static function collect(int $pid, int $startedAt, string $procRoot = '/proc'): array
    {
        $fdDirectory = rtrim($procRoot, '/') . '/' . $pid . '/fd';
        $openFdCount = null;
        $socketCount = null;

        if (is_dir($fdDirectory)) {
            $entries = glob($fdDirectory . '/*');
            if (is_array($entries)) {
                $openFdCount = count($entries);
                $socketCount = 0;
                foreach ($entries as $entry) {
                    $target = @readlink($entry);
                    if (is_string($target) && strpos($target, 'socket:[') === 0) {
                        ++$socketCount;
                    }
                }
            }
        }

        return [
            'pid' => $pid,
            'uptimeSeconds' => max(0, time() - $startedAt),
            'memoryBytes' => memory_get_usage(true),
            'peakMemoryBytes' => memory_get_peak_usage(true),
            'openFdCount' => $openFdCount,
            'tcpSocketCount' => $socketCount,
        ];
    }
}
