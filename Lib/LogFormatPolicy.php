<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class LogFormatPolicy
{
    public static function template(bool $phalcon5): string
    {
        return $phalcon5
            ? '[%date%][%level%] %message%'
            : '[%date%][%type%] %message%';
    }

    public static function encode($data): string
    {
        try {
            $encoded = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            return is_string($encoded) ? $encoded : '';
        } catch (\Throwable $e) {
            return print_r($data, true);
        }
    }
}
