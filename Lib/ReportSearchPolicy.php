<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class ReportSearchPolicy
{
    public static function isValid(string $search): bool
    {
        if ($search === '') {
            return false;
        }

        $decoded = json_decode($search, true);
        if (!is_array($decoded)) {
            return false;
        }

        $dateRange = $decoded['dateRangeSelector'] ?? null;
        return is_string($dateRange) && trim($dateRange) !== '';
    }
}
