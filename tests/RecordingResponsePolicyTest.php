<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/DownloadHeaderPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\DownloadHeaderPolicy;

function assertResponsePolicySame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$ascii = DownloadHeaderPolicy::attachment('call"\r\nInjected: yes.mp3');
assertResponsePolicySame(false, strpos($ascii, "\r") !== false, 'CR stripped');
assertResponsePolicySame(false, strpos($ascii, "\n") !== false, 'LF stripped');
assertResponsePolicySame(false, strpos($ascii, 'Injected:') !== false, 'hostile token stripped');
assertResponsePolicySame(true, strpos($ascii, 'attachment; filename="') === 0, 'quoted fallback');
assertResponsePolicySame(true, strpos($ascii, "filename*=UTF-8''") !== false, 'RFC 5987 name');

$unicode = DownloadHeaderPolicy::attachment('звонок клиента.mp3');
assertResponsePolicySame(true, strpos($unicode, '%D0%B7') !== false, 'Unicode encoded');

$controller = file_get_contents(dirname(__DIR__) . '/Lib/RestAPI/Controllers/ApiController.php');
if (!is_string($controller)) {
    throw new RuntimeException('Cannot read ApiController');
}

foreach (['RecordingPathPolicy', 'RecordingArchiveBuilder', 'Directories::AST_MONITOR_DIR'] as $needle) {
    assertResponsePolicySame(true, strpos($controller, $needle) !== false, 'controller uses ' . $needle);
}

assertResponsePolicySame(0, preg_match('/(?<!f)passthru\s*\(/', $controller), 'controller forbids shell passthru');
assertResponsePolicySame(0, preg_match('/(?<!shell_)exec\s*\(/', $controller), 'controller forbids exec');
foreach (['shell_exec(', 'system('] as $forbidden) {
    assertResponsePolicySame(false, strpos($controller, $forbidden) !== false, 'controller forbids ' . $forbidden);
}

assertResponsePolicySame(true, strpos($controller, 'finally') !== false, 'stream cleanup uses finally');
assertResponsePolicySame(true, strpos($controller, 'X-Content-Type-Options') !== false, 'nosniff response');

echo "RecordingResponsePolicyTest: OK\n";
