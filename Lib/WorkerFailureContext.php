<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use Throwable;

final class WorkerFailureContext
{
    private const METRIC_KEYS = [
        'pid',
        'uptimeSeconds',
        'memoryBytes',
        'peakMemoryBytes',
        'openFdCount',
        'tcpSocketCount',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function make(string $operation, Throwable $error, array $metrics, int $elapsedMs): array
    {
        $context = [
            'event' => 'worker_dependency_failure',
            'operation' => $operation,
            'errorClass' => get_class($error),
            'errorCategory' => 'dependency_failure',
            'elapsedMs' => max(0, $elapsedMs),
        ];

        foreach (self::METRIC_KEYS as $key) {
            if (array_key_exists($key, $metrics)) {
                $context[$key] = $metrics[$key];
            }
        }

        return $context;
    }
}
