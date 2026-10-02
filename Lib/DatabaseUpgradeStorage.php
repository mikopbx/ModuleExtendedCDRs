<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use RuntimeException;

/** Keep the complete SQLite directory (including WAL/journals) outside Core's rm/cp paths. */
final class DatabaseUpgradeStorage
{
    private string $moduleDir;
    private string $holdingDir;

    public function __construct(string $moduleDir)
    {
        $this->moduleDir = rtrim($moduleDir, '/');
        $this->holdingDir = dirname($this->moduleDir) . '/.' . basename($this->moduleDir) . '-upgrade';
    }

    public function preserve(): void
    {
        $this->locked(function (): void {
            $source = $this->moduleDir . '/db';
            $saved = $this->holdingDir . '/db';
            if (is_dir($saved)) {
                if ($this->hasDatabase($source)) {
                    throw new RuntimeException('Two database directories exist; preserved data retained at ' . $saved);
                }
                return;
            }
            if (!is_dir($source)) {
                return;
            }
            $this->move($source, $saved);
        });
    }

    /** Older installers may already have copied db to Core's Backup directory. */
    public function adoptLegacyBackup(): void
    {
        $this->locked(function (): void {
            $backup = dirname($this->moduleDir) . '/Backup/' . basename($this->moduleDir) . '/db';
            if (!is_dir($this->holdingDir . '/db') && $this->hasDatabase($backup)) {
                if ($this->hasDatabase($this->moduleDir . '/db')) {
                    throw new RuntimeException('Both installed and backup databases exist; refusing to overwrite either');
                }
                $this->move($backup, $this->holdingDir . '/db');
            }
        });
    }

    public function restore(): void
    {
        $this->locked(function (): void {
            $saved = $this->holdingDir . '/db';
            $target = $this->moduleDir . '/db';
            if (!is_dir($saved)) {
                return;
            }
            if (is_dir($target)) {
                if ($this->hasDatabase($target)) {
                    throw new RuntimeException('Refusing to replace an existing database; original retained at ' . $saved);
                }
                // Retain the package skeleton too, rather than deleting unknown files.
                $this->move($target, $this->holdingDir . '/package-' . bin2hex(random_bytes(6)));
            }
            $this->move($saved, $target);
        });
    }

    private function hasDatabase(string $dir): bool
    {
        return (bool) glob($dir . '/*.db*');
    }

    private function move(string $source, string $target): void
    {
        if (is_link($source) || file_exists($target) || is_link($target)) {
            throw new RuntimeException('Unsafe database move: ' . $source . ' -> ' . $target);
        }
        $sourceStat = stat($source);
        $targetStat = stat(dirname($target));
        if ($sourceStat['dev'] !== $targetStat['dev']) {
            throw new RuntimeException('Database preservation requires the same filesystem');
        }
        if (!rename($source, $target)) {
            throw new RuntimeException('Cannot move database directory: ' . $source);
        }
    }

    private function locked(callable $operation): void
    {
        if (!is_dir($this->holdingDir) && !mkdir($this->holdingDir, 0700, true) && !is_dir($this->holdingDir)) {
            throw new RuntimeException('Cannot create database preservation directory');
        }
        $handle = fopen($this->holdingDir . '/lock', 'c');
        if ($handle === false) throw new RuntimeException('Cannot open database preservation lock');
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('Database preservation already in progress');
            $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
