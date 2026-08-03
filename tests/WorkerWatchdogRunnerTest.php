<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/WorkerWatchdogRunner.php';

use Modules\ModuleExtendedCDRs\Lib\WorkerWatchdogRunner;

function assertWatchdogRunner($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$started = [];
$signalled = [];
$events = [];
$pids = [
    'WorkerA' => '',
    'WorkerB' => '42',
    'WorkerC' => '11 22 33',
];

$status = WorkerWatchdogRunner::run(
    array_keys($pids),
    static function (string $worker) use ($pids): string {
        return $pids[$worker];
    },
    static function (string $worker) use (&$started): void {
        $started[] = $worker;
    },
    static function (array $duplicates) use (&$signalled): void {
        $signalled[] = $duplicates;
    },
    static function (array $event) use (&$events): void {
        $events[] = $event;
    }
);

assertWatchdogRunner(0, $status, 'successful run status');
assertWatchdogRunner(['WorkerA'], $started, 'missing worker is started once');
assertWatchdogRunner([[11, 22]], $signalled, 'all duplicate pids except canonical final pid are signalled');
assertWatchdogRunner('worker_watchdog_phase', $events[0]['event'], 'stable diagnostic event');
assertWatchdogRunner('WorkerA', $events[0]['worker'], 'diagnostic identifies worker');
assertWatchdogRunner(true, isset($events[0]['elapsedMs']), 'diagnostic contains duration');

$failureEvents = [];
$failureStatus = WorkerWatchdogRunner::run(
    ['BrokenWorker'],
    static function (): string {
        throw new RuntimeException('secret /recordings/call.wav 79990001122');
    },
    static function (): void {
    },
    static function (): void {
    },
    static function (array $event) use (&$failureEvents): void {
        $failureEvents[] = $event;
    }
);
assertWatchdogRunner(1, $failureStatus, 'dependency exception returns failure');
assertWatchdogRunner('failed', $failureEvents[0]['outcome'], 'failure outcome is logged');
assertWatchdogRunner(false, isset($failureEvents[0]['message']), 'dependency message is not logged');

echo "WorkerWatchdogRunnerTest: OK\n";
