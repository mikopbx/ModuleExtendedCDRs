<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class RecordingPathResult
{
    private bool $allowed;
    private string $reason;
    private int $status;
    private ?string $path;
    private ?string $mimeType;
    private ?string $downloadName;

    private function __construct(
        bool $allowed,
        string $reason,
        int $status,
        ?string $path,
        ?string $mimeType,
        ?string $downloadName
    ) {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->status = $status;
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->downloadName = $downloadName;
    }

    public static function allowed(string $path, string $mimeType, string $downloadName): self
    {
        return new self(true, 'allowed', 200, $path, $mimeType, $downloadName);
    }

    public static function rejected(string $reason, int $status): self
    {
        return new self(false, $reason, $status, null, null, null);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function mimeType(): ?string
    {
        return $this->mimeType;
    }

    public function downloadName(): ?string
    {
        return $this->downloadName;
    }
}
