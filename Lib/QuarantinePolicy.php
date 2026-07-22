<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class QuarantinePolicy
{
    public const MAX_ATTEMPTS = 6;
    public const BASE_RETRY_SECONDS = 60;
    public const MAX_RETRY_SECONDS = 3600;

    /** @return array<string,mixed> */
    public static function failed(array $current, string $reason, int $now): array
    {
        $attempts = (int)($current['attempts'] ?? 0) + 1;
        $delay = min(self::MAX_RETRY_SECONDS, self::BASE_RETRY_SECONDS * (2 ** ($attempts - 1)));
        return [
            'reason' => $reason,
            'attempts' => $attempts,
            'firstFailureAt' => (int)($current['firstFailureAt'] ?? $now),
            'lastFailureAt' => $now,
            'nextRetryAt' => $now + $delay,
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'manual' : 'pending',
        ];
    }

    /** @return array<string,mixed> */
    public static function resolved(array $current, int $now): array
    {
        $current['status'] = 'resolved';
        $current['nextRetryAt'] = 0;
        $current['lastFailureAt'] = $now;
        return $current;
    }
}
