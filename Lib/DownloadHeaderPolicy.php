<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class DownloadHeaderPolicy
{
    public static function attachment(string $filename): string
    {
        $normalized = str_replace('\\', '/', $filename);
        $separator = strrpos($normalized, '/');
        $basename = $separator === false ? $normalized : substr($normalized, $separator + 1);
        $clean = str_replace(["\r", "\n", '"', "\0"], '', $basename);
        if ($clean === '') {
            $clean = 'download';
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $clean);
        if (!is_string($fallback) || $fallback === '') {
            $fallback = 'download';
        }

        return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($clean);
    }
}
