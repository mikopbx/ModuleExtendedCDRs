<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class TemporaryFileGuard
{
    /** @var array<string, true> */
    private array $paths = [];

    public function track(string $path): void
    {
        if ($path !== '') {
            $this->paths[$path] = true;
        }
    }

    public function forget(string $path): void
    {
        unset($this->paths[$path]);
    }

    public function cleanup(): void
    {
        foreach (array_keys($this->paths) as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
            unset($this->paths[$path]);
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
