<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/LogFormatPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\LogFormatPolicy;

function assertLogFormatSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertLogFormatSame('[%date%][%level%] %message%', LogFormatPolicy::template(true), 'Phalcon 5 template');
assertLogFormatSame('[%date%][%type%] %message%', LogFormatPolicy::template(false), 'Phalcon 4 template');
assertLogFormatSame('"offset (+3)"', LogFormatPolicy::encode('offset (+3)'), 'plus sign is preserved');

echo "LogFormatPolicyTest: OK\n";
