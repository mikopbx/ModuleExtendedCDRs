<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

final class RecordingPathPolicy
{
    private string $recordingRoot;

    /** @var array<string,string> */
    private array $mimeTypes;

    /**
     * @param array<string,string> $mimeTypes
     */
    public function __construct(string $recordingRoot, array $mimeTypes = [])
    {
        $realRoot = realpath($recordingRoot);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \InvalidArgumentException('Recording root does not exist');
        }

        $this->recordingRoot = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->mimeTypes = $mimeTypes ?: [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
        ];
    }

    public function validate(string $candidate): RecordingPathResult
    {
        if ($candidate === '' || strpos($candidate, "\0") !== false
            || preg_match('~^[a-z][a-z0-9+.-]*://~i', $candidate) === 1) {
            return RecordingPathResult::rejected('invalid_recording_path', 404);
        }

        $realCandidate = realpath($candidate);
        if ($realCandidate === false || !is_file($realCandidate)) {
            return RecordingPathResult::rejected('missing_recording', 404);
        }

        if (strpos($realCandidate . DIRECTORY_SEPARATOR, $this->recordingRoot) !== 0) {
            return RecordingPathResult::rejected('outside_recording_root', 404);
        }

        $extension = strtolower((string) pathinfo($realCandidate, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, $this->mimeTypes)) {
            return RecordingPathResult::rejected('unsupported_media_type', 415);
        }

        if (!is_readable($realCandidate)) {
            return RecordingPathResult::rejected('missing_recording', 404);
        }

        return RecordingPathResult::allowed(
            $realCandidate,
            $this->mimeTypes[$extension],
            basename($realCandidate)
        );
    }
}
