<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class ModuleWatchdogCommand
{
    public static function build(string $busybox, string $php, string $moduleDir, int $timeoutSeconds): string
    {
        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('Watchdog timeout must be positive');
        }

        return implode(' ', [
            escapeshellarg($busybox),
            'timeout',
            (string) $timeoutSeconds,
            escapeshellarg($php),
            '-f',
            escapeshellarg(rtrim($moduleDir, '/') . '/bin/safe.php'),
        ]);
    }
}
