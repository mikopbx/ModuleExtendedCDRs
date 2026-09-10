<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/CdrQueryBuilder.php';

use Modules\ModuleExtendedCDRs\Lib\CdrQueryBuilder;

// Removing the per-leg answer check must include the other employee's answer and fail.
$db = new PDO('sqlite::memory:');
$db->exec("CREATE TABLE cdr_general (linkedid TEXT, srcIndex TEXT, dstIndex TEXT, billsec INTEGER, disposition TEXT, is_app TEXT)");
$insert = $db->prepare('INSERT INTO cdr_general VALUES (?, ?, ?, ?, ?, ?)');
foreach ([
    ['answered204', 'client', '204', 10, 'ANSWERED', '0'],
    ['answered203', 'client', '204', 0, 'NOANSWER', '0'],
    ['answered203', 'client', '203', 10, 'ANSWERED', '0'],
    ['missed', 'client', '204', 0, 'NOANSWER', '0'],
    ['transferAnswered', 'client', '203', 10, 'ANSWERED', '0'],
    ['transferAnswered', 'client', '204', 5, 'ANSWER', ''],
    ['transferMissed', 'client', '203', 10, 'ANSWERED', '0'],
    ['transferMissed', 'client', '204', 0, 'NOANSWER', '0'],
    ['outgoing', '204', 'client', 20, 'ANSWERED', null],
    ['outgoingFailed', '204', 'client', 0, 'BUSY', '0'],
    ['application', '204', 'client', 10, 'ANSWERED', '1'],
    ['falseDuration', 'client', '204', 10, 'NOANSWER', '0'],
    ['zeroDuration', 'client', '204', 0, 'ANSWERED', '0'],
] as $row) {
    $insert->execute($row);
}
function matchingCalls(PDO $db, array $numbers, bool $enabled): array
{
    $builder = (new CdrQueryBuilder())->whereNumbers($numbers, 'Employee', $enabled);
    $stmt = $db->prepare('SELECT DISTINCT linkedid FROM cdr_general WHERE ' . ($builder->getCondition() ?: '1=1') . ' ORDER BY linkedid');
    $stmt->execute($builder->getBindParams());
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
function checkConversation($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . json_encode($actual));
    }
}
checkConversation(['answered204', 'outgoing', 'transferAnswered'], matchingCalls($db, ['204'], true), 'Only connected legs of 204');
checkConversation(['answered203', 'answered204', 'outgoing', 'transferAnswered', 'transferMissed'], matchingCalls($db, ['204', '203'], true), 'Any selected employee');
checkConversation(10, count(matchingCalls($db, ['204'], false)), 'Disabled option preserves ringing calls');
checkConversation(10, count(matchingCalls($db, [], true)), 'No employee selection does not filter');
echo "EmployeeConversationFilterTest: OK\n";
