<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class WorkerWatchdogLease
{
    /** @var resource|null */
    private $handle;

    /**
     * @param resource $handle
     */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    public static function tryAcquire(string $path, int $pid, int $startedAt): ?self
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create watchdog lock directory: {$directory}");
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open watchdog lock: {$path}");
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        $payload = json_encode(['pid' => $pid, 'startedAt' => $startedAt], JSON_UNESCAPED_SLASHES);
        if ($payload === false || !ftruncate($handle, 0) || fseek($handle, 0) !== 0 || fwrite($handle, $payload) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new \RuntimeException("Unable to write watchdog lock diagnostics: {$path}");
        }
        fflush($handle);

        return new self($handle);
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
