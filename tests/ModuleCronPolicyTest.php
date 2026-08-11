<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/ModuleWatchdogCommand.php';

use Modules\ModuleExtendedCDRs\Lib\ModuleWatchdogCommand;

function assertCronPolicy($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$root = sys_get_temp_dir() . '/extended cdr cron ' . bin2hex(random_bytes(4));
$moduleDir = $root . '/module path';
mkdir($moduleDir . '/bin', 0775, true);
$fakeBusybox = $root . '/fake busybox';
$fakePhp = $root . '/fake php';
$capture = $root . '/captured.json';

file_put_contents($fakeBusybox, "#!/bin/sh\nprintf '%s\\n' \"\$@\" > " . escapeshellarg($capture) . "\n");
file_put_contents($fakePhp, "#!/bin/sh\nexit 0\n");
file_put_contents($moduleDir . '/bin/safe.php', "<?php\n");
chmod($fakeBusybox, 0755);
chmod($fakePhp, 0755);

try {
    $command = ModuleWatchdogCommand::build($fakeBusybox, $fakePhp, $moduleDir, 50);
    exec($command, $output, $status);
    assertCronPolicy(0, $status, 'built command is executable');
    assertCronPolicy(
        ['timeout', '50', $fakePhp, '-f', $moduleDir . '/bin/safe.php'],
        file($capture, FILE_IGNORE_NEW_LINES),
        'timeout command receives exact escaped arguments'
    );
} finally {
    @unlink($capture);
    @unlink($moduleDir . '/bin/safe.php');
    @rmdir($moduleDir . '/bin');
    @rmdir($moduleDir);
    @unlink($fakePhp);
    @unlink($fakeBusybox);
    @rmdir($root);
}

echo "ModuleCronPolicyTest: OK\n";
