<?php

declare(strict_types=1);

function assertPrivateRoute(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$conf = file_get_contents(dirname(__DIR__) . '/Lib/ExtendedCDRsConf.php');
$controller = file_get_contents(dirname(__DIR__) . '/Lib/RestAPI/Controllers/ApiController.php');
assertPrivateRoute(is_string($conf), 'Cannot read route configuration');
assertPrivateRoute(is_string($controller), 'Cannot read API controller');

$actions = ['downloads', 'exportHistory', 'exportHistoryDetail', 'recordsAction', 'exportOutgoingEmployeeCalls'];
foreach ($actions as $action) {
    $pattern = '/\[ApiController::class,\s*\'' . preg_quote($action, '/')
        . '\'[^\]]*,\s*false\s*\]/';
    assertPrivateRoute(preg_match($pattern, $conf) === 1, $action . ' must require authentication');
}

foreach (['PHPSESSID=', 'boffart.miko.ru', 'Authorization: Bearer', '/storage/usbdisk'] as $forbidden) {
    assertPrivateRoute(strpos($controller, $forbidden) === false, 'Sensitive API example remains: ' . $forbidden);
}

echo "PrivateRoutesTest: OK\n";
