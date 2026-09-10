<?php
/** Run on a PBX: php EmployeeConversationDetailsTest.php '01/08/2026 - 10/09/2026' 204 */
require_once 'Globals.php';

use Modules\ModuleExtendedCDRs\Lib\GetReport;

$period = $argv[1] ?? '';
$employee = $argv[2] ?? '';
if ($period === '' || !ctype_digit($employee)) {
    throw new InvalidArgumentException('Provide a date range and an employee with answered calls containing other legs.');
}
$report = new GetReport();
$search = ['dateRangeSelector' => $period, 'additionalFilter' => $employee, 'globalSearch' => '', 'typeCall' => 'all-calls'];
$full = $report->history(json_encode($search));
$filtered = $report->history(json_encode($search + ['onlyEmployeeConversations' => true]));
if (empty($filtered->data)) {
    throw new RuntimeException('Fixture has no answered calls; choose another period/employee.');
}
$fullById = array_column($full->data, null, 'DT_RowId');
$expectedCallIds = [];
foreach ($full->data as $call) {
    foreach ($call[4] as $leg) {
        if (($leg['src_num'] === $employee || $leg['dst_num'] === $employee) && (int)$leg['billSecInt'] > 0
            && (string)$leg['stateCallIndex'] !== '7') {
            $expectedCallIds[] = $call['DT_RowId'];
            break;
        }
    }
}
$actualCallIds = array_column($filtered->data, 'DT_RowId');
sort($expectedCallIds);
sort($actualCallIds);
if ($actualCallIds !== $expectedCallIds || count($actualCallIds) !== (int)$filtered->recordsFiltered) {
    throw new RuntimeException('Call selection/count does not match answered employee legs.');
}
$removed = 0;
$verified = 0;
foreach ($filtered->data as $call) {
    $expected = array_values(array_filter($fullById[$call['DT_RowId']][4], static function ($leg) use ($employee) {
        return ($leg['src_num'] === $employee || $leg['dst_num'] === $employee)
            && (int)$leg['billSecInt'] > 0 && (string)$leg['stateCallIndex'] !== '7';
    }));
    $expectedIds = array_column($expected, 'id');
    $actualIds = array_column($call[4], 'id');
    sort($expectedIds);
    sort($actualIds);
    if ($expectedIds !== $actualIds) {
        throw new RuntimeException('Detail contains unrelated/unanswered CDRs: expected ' . count($expectedIds) . ', got ' . count($actualIds));
    }
    if ((int)$call['billsec'] !== array_sum(array_column($expected, 'billSecInt'))) {
        throw new RuntimeException('Summary duration includes excluded legs.');
    }
    $removed += count($fullById[$call['DT_RowId']][4]) - count($call[4]);
    $verified += count($call[4]);
}
if ($removed === 0) throw new RuntimeException('Fixture must contain unrelated/unanswered legs.');
echo "EmployeeConversationDetailsTest: OK, calls=" . count($filtered->data) . ", details=$verified, excluded=$removed\n";
