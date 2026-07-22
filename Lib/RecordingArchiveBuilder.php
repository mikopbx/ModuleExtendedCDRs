<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class RecordingArchiveBuilder
{
    private const DEFAULT_MAX_RECORDS = 2000;
    private const DEFAULT_MAX_BYTES = 2147483648;
    private const DEFAULT_MAX_CANDIDATES = 5000;
    private const MIN_FREE_BYTES = 67108864;

    private RecordingPathPolicy $policy;
    private string $tempRoot;
    private int $maxRecords;
    private int $maxBytes;
    private int $maxCandidates;

    public function __construct(
        RecordingPathPolicy $policy,
        string $tempRoot,
        int $maxRecords = self::DEFAULT_MAX_RECORDS,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
        int $maxCandidates = self::DEFAULT_MAX_CANDIDATES
    ) {
        if ($maxRecords < 1 || $maxBytes < 1 || $maxCandidates < 1) {
            throw new \InvalidArgumentException('Archive limits must be positive');
        }
        $this->policy = $policy;
        $this->tempRoot = rtrim($tempRoot, DIRECTORY_SEPARATOR);
        $this->maxRecords = $maxRecords;
        $this->maxBytes = $maxBytes;
        $this->maxCandidates = $maxCandidates;
    }

    /**
     * @param array<int,array{path:string,name:string}> $records
     */
    public function build(array $records): RecordingArchiveResult
    {
        $this->ensureTempRoot();
        $archivePath = $this->tempRoot . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.tar';
        $accepted = 0;
        $skipped = 0;
        $acceptedBytes = 0;
        $inspected = 0;
        $usedNames = [];

        try {
            $archive = new \PharData($archivePath);
            foreach ($records as $record) {
                $inspected++;
                if ($inspected > $this->maxCandidates) {
                    throw new \RuntimeException('archive_too_large');
                }
                if (!isset($record['path'], $record['name'])
                    || !is_string($record['path']) || !is_string($record['name'])) {
                    $skipped++;
                    continue;
                }

                $allowed = $this->policy->validate($record['path']);
                if (!$allowed->isAllowed() || $allowed->path() === null) {
                    $skipped++;
                    continue;
                }

                $fileSize = filesize($allowed->path());
                if ($fileSize === false) {
                    $skipped++;
                    continue;
                }
                if ($accepted >= $this->maxRecords || $acceptedBytes + $fileSize > $this->maxBytes) {
                    throw new \RuntimeException('archive_too_large');
                }
                $freeBytes = disk_free_space($this->tempRoot);
                if ($freeBytes !== false && $fileSize + self::MIN_FREE_BYTES > $freeBytes) {
                    throw new \RuntimeException('archive_too_large');
                }

                $extension = strtolower((string) pathinfo((string) $allowed->downloadName(), PATHINFO_EXTENSION));
                $entryName = $this->uniqueEntryName($record['name'], $extension, $usedNames);
                $archive->addFile($allowed->path(), $entryName);
                $accepted++;
                $acceptedBytes += $fileSize;
            }

            unset($archive);
            if ($accepted === 0) {
                @unlink($archivePath);
                throw new \RuntimeException('archive_has_no_valid_entries');
            }

            return new RecordingArchiveResult($archivePath, $accepted, $skipped);
        } catch (\RuntimeException $exception) {
            @unlink($archivePath);
            if (in_array($exception->getMessage(), ['archive_has_no_valid_entries', 'archive_too_large'], true)) {
                throw $exception;
            }
            throw new \RuntimeException('archive_build_failed', 0, $exception);
        } catch (\Throwable $exception) {
            @unlink($archivePath);
            throw new \RuntimeException('archive_build_failed', 0, $exception);
        }
    }

    private function ensureTempRoot(): void
    {
        if (!is_dir($this->tempRoot) && !mkdir($this->tempRoot, 0700, true) && !is_dir($this->tempRoot)) {
            throw new \RuntimeException('archive_build_failed');
        }
        @chmod($this->tempRoot, 0700);
    }

    /**
     * @param array<string,bool> $usedNames
     */
    private function uniqueEntryName(string $displayName, string $extension, array &$usedNames): string
    {
        $stem = preg_replace('/[^\pL\pN._-]+/u', '-', $displayName);
        if (!is_string($stem)) {
            $stem = '';
        }
        $stem = trim($stem, ".-_ \t\n\r\0\x0B");
        if ($stem === '') {
            $stem = 'recording';
        }
        // POSIX ustar entry names are limited to 100 bytes. Leave room for
        // duplicate suffixes and the validated extension.
        $stem = function_exists('mb_strcut') ? mb_strcut($stem, 0, 80, 'UTF-8') : substr($stem, 0, 80);

        $candidate = $stem . '.' . $extension;
        $suffix = 2;
        while (isset($usedNames[$candidate])) {
            $candidate = $stem . '-' . $suffix . '.' . $extension;
            $suffix++;
        }
        $usedNames[$candidate] = true;

        return $candidate;
    }
}
