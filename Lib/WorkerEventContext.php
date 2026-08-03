<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class WorkerEventContext
{
    /**
     * @return array<string, bool|string>
     */
    public static function make(array $request, string $outcome): array
    {
        return [
            'event' => 'worker_event',
            'action' => self::identifier((string) ($request['action'] ?? '')),
            'function' => self::identifier((string) ($request['function'] ?? '')),
            'needsReply' => isset($request['need-ret']),
            'outcome' => self::identifier($outcome),
        ];
    }

    private static function identifier(string $value): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $value), 0, 64);
    }
}
