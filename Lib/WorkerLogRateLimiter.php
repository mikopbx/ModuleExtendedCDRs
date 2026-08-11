<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class WorkerLogRateLimiter
{
    public static function shouldLog(string $path, int $now, int $intervalSeconds): bool
    {
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }

        $contents = stream_get_contents($handle);
        $lastLoggedAt = is_string($contents) ? (int) trim($contents) : 0;
        $allowed = $lastLoggedAt === 0 || ($now - $lastLoggedAt) >= $intervalSeconds;
        if ($allowed) {
            ftruncate($handle, 0);
            fseek($handle, 0);
            fwrite($handle, (string) $now);
            fflush($handle);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        return $allowed;
    }
}
