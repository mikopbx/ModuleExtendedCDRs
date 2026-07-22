<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class BatchPersistenceResult
{
    /** @return array{ok:bool,inserted:int,updated:int,errorCategory:string,message:string} */
    public static function success(int $inserted, int $updated): array
    {
        return [
            'ok' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'errorCategory' => '',
            'message' => '',
        ];
    }

    /** @return array{ok:bool,inserted:int,updated:int,errorCategory:string,message:string} */
    public static function failure(string $category, string $message = ''): array
    {
        return [
            'ok' => false,
            'inserted' => 0,
            'updated' => 0,
            'errorCategory' => $category,
            'message' => $message,
        ];
    }
}
