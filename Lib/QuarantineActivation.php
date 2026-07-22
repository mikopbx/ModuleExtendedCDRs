<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class QuarantineActivation
{
    /**
     * @param string[] $current
     * @param string[] $committed
     * @return string[]
     */
    public static function afterCommit(array $current, array $committed): array
    {
        return array_values(array_unique(array_merge($current, $committed)));
    }
}
