<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/ReportSearchPolicy.php';

use Modules\ModuleExtendedCDRs\Lib\ReportSearchPolicy;

function assertReportSearchSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assertReportSearchSame(false, ReportSearchPolicy::isValid(''), 'empty rejected');
assertReportSearchSame(false, ReportSearchPolicy::isValid('{}'), 'missing date range rejected');
assertReportSearchSame(false, ReportSearchPolicy::isValid('{bad json'), 'invalid JSON rejected');
assertReportSearchSame(false, ReportSearchPolicy::isValid('{"dateRangeSelector":null}'), 'null date range rejected');
assertReportSearchSame(false, ReportSearchPolicy::isValid('{"dateRangeSelector":[]}'), 'array date range rejected');
assertReportSearchSame(false, ReportSearchPolicy::isValid('{"dateRangeSelector":""}'), 'empty date range rejected');
assertReportSearchSame(
    true,
    ReportSearchPolicy::isValid('{"dateRangeSelector":"22/07/2026 - 22/07/2026"}'),
    'valid date range accepted'
);

$controller = (string) file_get_contents(dirname(__DIR__) . '/Lib/RestAPI/Controllers/ApiController.php');
if (substr_count($controller, 'ReportSearchPolicy::isValid') < 4) {
    throw new RuntimeException('All report and archive endpoints must validate search JSON');
}

echo "ReportSearchPolicyTest: OK\n";
