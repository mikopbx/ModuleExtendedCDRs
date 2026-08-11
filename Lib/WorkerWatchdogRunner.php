<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use Throwable;

final class WorkerWatchdogRunner
{
    public static function run(
        array $workers,
        callable $findPids,
        callable $startWorker,
        callable $signalDuplicates,
        callable $log
    ): int {
        foreach ($workers as $worker) {
            $startedAt = microtime(true);
            try {
                $pidText = trim((string) $findPids($worker));
                $pids = $pidText === ''
                    ? []
                    : array_values(array_filter(array_map('intval', preg_split('/\s+/', $pidText) ?: [])));

                if ($pids === []) {
                    $startWorker($worker);
                    $outcome = 'started';
                } elseif (count($pids) > 1) {
                    $duplicates = array_slice($pids, 0, -1);
                    $signalDuplicates($duplicates);
                    $outcome = 'duplicates_signalled';
                } else {
                    $outcome = 'running';
                }

                $log(self::event($worker, $pids, $outcome, $startedAt));
            } catch (Throwable $error) {
                $log(self::event($worker, [], 'failed', $startedAt) + [
                    'errorClass' => get_class($error),
                ]);
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(string $worker, array $pids, string $outcome, float $startedAt): array
    {
        return [
            'event' => 'worker_watchdog_phase',
            'worker' => $worker,
            'pids' => $pids,
            'outcome' => $outcome,
            'elapsedMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }
}
