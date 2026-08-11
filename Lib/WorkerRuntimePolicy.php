<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class WorkerRuntimePolicy
{
    private const WATCHDOG_DEADLINE_SECONDS = 40;
    private const OUTER_TIMEOUT_SECONDS = 50;
    private const HEALTH_LOG_INTERVAL_SECONDS = 300;

    public static function watchdogDeadlineSeconds(): int
    {
        return self::WATCHDOG_DEADLINE_SECONDS;
    }

    public static function outerTimeoutSeconds(): int
    {
        return self::OUTER_TIMEOUT_SECONDS;
    }

    public static function shouldLogHealth(int $now, int $lastLoggedAt): bool
    {
        return $lastLoggedAt === 0 || ($now - $lastLoggedAt) >= self::HEALTH_LOG_INTERVAL_SECONDS;
    }
}
