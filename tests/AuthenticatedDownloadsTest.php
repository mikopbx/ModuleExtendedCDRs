<?php

declare(strict_types=1);

function assertAuthenticatedDownload(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$source = file_get_contents(dirname(__DIR__) . '/public/assets/js/src/module-export-records-index.js');
$compiled = file_get_contents(dirname(__DIR__) . '/public/assets/js/module-export-records-index.js');
assertAuthenticatedDownload(is_string($source), 'Cannot read download JavaScript');
assertAuthenticatedDownload(is_string($compiled), 'Cannot read compiled download JavaScript');

foreach ([
    'authenticatedDownload(url',
    "headers.Authorization = `Bearer \${TokenManager.accessToken}`",
    'response.blob()',
    'URL.createObjectURL(blob)',
    "response.headers.get('Content-Disposition')",
] as $required) {
    assertAuthenticatedDownload(strpos($source, $required) !== false, 'Missing authenticated download behavior: ' . $required);
}

foreach ([
    "window.open(url, '_blank')",
    "window.open('/pbxcore/api/modules/'+className+'/downloads?",
] as $forbidden) {
    assertAuthenticatedDownload(strpos($source, $forbidden) === false, 'Protected download bypasses Bearer authentication');
}

assertAuthenticatedDownload(
    substr_count($source, 'ModuleExtendedCDRs.authenticatedDownload(') >= 3,
    'XLS/PDF and recording archive downloads must use authenticated transport'
);
assertAuthenticatedDownload(
    strpos($compiled, 'Authenticated download failed') !== false
        && strpos($compiled, 'response.blob()') !== false,
    'Compiled asset is missing authenticated download behavior'
);
assertAuthenticatedDownload(
    preg_match('/\brequire\s*\(/', $compiled) === 0,
    'Browser asset must not depend on CommonJS require()'
);

echo "AuthenticatedDownloadsTest: OK\n";
