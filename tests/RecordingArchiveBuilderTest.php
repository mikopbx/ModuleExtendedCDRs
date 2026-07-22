<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/RecordingPathResult.php';
require_once dirname(__DIR__) . '/Lib/RecordingPathPolicy.php';
require_once dirname(__DIR__) . '/Lib/RecordingArchiveResult.php';
require_once dirname(__DIR__) . '/Lib/RecordingArchiveBuilder.php';

use Modules\ModuleExtendedCDRs\Lib\RecordingArchiveBuilder;
use Modules\ModuleExtendedCDRs\Lib\RecordingPathPolicy;

function assertArchiveSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function removeArchiveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . '/' . $item;
        if (is_dir($target) && !is_link($target)) {
            removeArchiveTree($target);
        } else {
            unlink($target);
        }
    }
    rmdir($path);
}

$base = sys_get_temp_dir() . '/extended-cdr-archive-' . bin2hex(random_bytes(6));
$monitor = $base . '/monitor';
$temp = $base . '/archives';
mkdir($monitor, 0700, true);
file_put_contents($monitor . '/one.mp3', 'one');
file_put_contents($monitor . '/two.mp3', 'two');
file_put_contents($monitor . '/voice.wav', 'wave');

try {
    $policy = new RecordingPathPolicy($monitor);
    $builder = new RecordingArchiveBuilder($policy, $temp);
    $marker = $base . '/PWNED';

    $result = $builder->build([
        ['path' => $monitor . '/one.mp3', 'name' => 'call;touch ' . $marker],
        ['path' => $monitor . '/two.mp3', 'name' => 'call;touch ' . $marker],
        ['path' => $monitor . '/voice.wav', 'name' => '../Голос клиента'],
        ['path' => $base . '/outside.mp3', 'name' => 'outside'],
        ['path' => $monitor . '/missing.mp3', 'name' => 'missing'],
    ]);

    assertArchiveSame(3, $result->acceptedCount(), 'valid files accepted');
    assertArchiveSame(2, $result->skippedCount(), 'invalid files skipped');
    assertArchiveSame(true, is_file($result->path()), 'tar created');
    assertArchiveSame(false, file_exists($marker), 'shell metacharacters not executed');

    $archive = new PharData($result->path());
    $names = [];
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $names[] = $file->getFilename();
    }
    sort($names);
    assertArchiveSame(3, count($names), 'three flat entries');
    foreach ($names as $name) {
        assertArchiveSame(false, strpos($name, '/') !== false, 'entry has no directory');
        assertArchiveSame(false, strpos($name, ';') !== false, 'entry has no shell punctuation');
    }
    assertArchiveSame(true, count(array_filter($names, static function (string $name): bool {
        return substr($name, -6) === '-2.mp3';
    })) === 1, 'duplicate receives deterministic suffix');

    unlink($result->path());

    try {
        (new RecordingArchiveBuilder($policy, $temp, 1, 1024))->build([
            ['path' => $monitor . '/one.mp3', 'name' => 'one'],
            ['path' => $monitor . '/two.mp3', 'name' => 'two'],
        ]);
        throw new RuntimeException('record quota did not fail');
    } catch (RuntimeException $exception) {
        assertArchiveSame('archive_too_large', $exception->getMessage(), 'record quota reason');
    }

    try {
        (new RecordingArchiveBuilder($policy, $temp, 10, 5))->build([
            ['path' => $monitor . '/one.mp3', 'name' => 'one'],
            ['path' => $monitor . '/voice.wav', 'name' => 'voice'],
        ]);
        throw new RuntimeException('byte quota did not fail');
    } catch (RuntimeException $exception) {
        assertArchiveSame('archive_too_large', $exception->getMessage(), 'byte quota reason');
    }

    try {
        $builder->build([
            ['path' => $monitor . '/missing.mp3', 'name' => 'missing'],
        ]);
        throw new RuntimeException('empty archive did not fail');
    } catch (RuntimeException $exception) {
        assertArchiveSame('archive_has_no_valid_entries', $exception->getMessage(), 'empty archive reason');
    }

    assertArchiveSame([], glob($temp . '/*.tar') ?: [], 'no partial archives left');
} finally {
    removeArchiveTree($base);
}

echo "RecordingArchiveBuilderTest: OK\n";
