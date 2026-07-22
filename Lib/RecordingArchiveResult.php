<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class RecordingArchiveResult
{
    private string $path;
    private int $acceptedCount;
    private int $skippedCount;

    public function __construct(string $path, int $acceptedCount, int $skippedCount)
    {
        $this->path = $path;
        $this->acceptedCount = $acceptedCount;
        $this->skippedCount = $skippedCount;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function acceptedCount(): int
    {
        return $this->acceptedCount;
    }

    public function skippedCount(): int
    {
        return $this->skippedCount;
    }
}
