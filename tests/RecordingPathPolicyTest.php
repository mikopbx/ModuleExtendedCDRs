<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/RecordingPathResult.php';
require_once dirname(__DIR__) . '/Lib/RecordingPathPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\RecordingPathPolicy;

function assertPathPolicySame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function removePathPolicyTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . DIRECTORY_SEPARATOR . $item;
        if (is_link($target) || is_file($target)) {
            unlink($target);
        } elseif (is_dir($target)) {
            removePathPolicyTree($target);
        }
    }
    rmdir($path);
}

$base = sys_get_temp_dir() . '/extended-cdr-path-policy-' . bin2hex(random_bytes(6));
$monitor = $base . '/monitor';
$sibling = $base . '/monitor-old';
mkdir($monitor . '/2026/07', 0700, true);
mkdir($sibling, 0700, true);

file_put_contents($monitor . '/2026/07/call.mp3', 'mp3');
file_put_contents($monitor . '/call.wav', 'wav');
file_put_contents($monitor . '/call.webm', 'webm');
file_put_contents($monitor . '/notes.txt', 'text');
file_put_contents($sibling . '/secret.mp3', 'secret');
symlink($sibling . '/secret.mp3', $monitor . '/escape.mp3');

try {
    $policy = new RecordingPathPolicy($monitor);

    $valid = $policy->validate($monitor . '/2026/07/call.mp3');
    assertPathPolicySame(true, $valid->isAllowed(), 'valid recording allowed');
    assertPathPolicySame('audio/mpeg', $valid->mimeType(), 'MP3 MIME');
    assertPathPolicySame('call.mp3', $valid->downloadName(), 'download basename');
    assertPathPolicySame(realpath($monitor . '/2026/07/call.mp3'), $valid->path(), 'canonical path');

    assertPathPolicySame('audio/wav', $policy->validate($monitor . '/call.wav')->mimeType(), 'WAV MIME');
    assertPathPolicySame('audio/webm', $policy->validate($monitor . '/call.webm')->mimeType(), 'WEBM MIME');

    $cases = [
        [$monitor . '/missing.mp3', 'missing_recording', 404],
        [$monitor, 'missing_recording', 404],
        [$sibling . '/secret.mp3', 'outside_recording_root', 404],
        [$monitor . '/../monitor-old/secret.mp3', 'outside_recording_root', 404],
        [$monitor . '/escape.mp3', 'outside_recording_root', 404],
        ['phar://' . $monitor . '/2026/07/call.mp3', 'invalid_recording_path', 404],
        [$monitor . "/bad\0.mp3", 'invalid_recording_path', 404],
        [$monitor . '/notes.txt', 'unsupported_media_type', 415],
    ];

    foreach ($cases as $case) {
        [$candidate, $reason, $status] = $case;
        $result = $policy->validate($candidate);
        assertPathPolicySame(false, $result->isAllowed(), 'candidate rejected: ' . $reason);
        assertPathPolicySame($reason, $result->reason(), 'safe rejection reason');
        assertPathPolicySame($status, $result->status(), 'rejection status');
        assertPathPolicySame(null, $result->path(), 'rejected path not retained');
        assertPathPolicySame(null, $result->downloadName(), 'rejected basename not retained');
    }
} finally {
    removePathPolicyTree($base);
}

echo "RecordingPathPolicyTest: OK\n";
