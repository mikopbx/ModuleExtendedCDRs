<?php
/**
 * Test script for getCountCdr optimization.
 * Compares results from direct query vs cached query to ensure consistency.
 *
 * Usage: php test_getCountCdr.php
 */

require_once('Globals.php');

use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Models\DailyCallStats;

class GetCountCdrTest
{
    private ConnectorDB $connector;
    private int $passed = 0;
    private int $failed = 0;
    private array $testResults = [];

    public function __construct()
    {
        $this->connector = new ConnectorDB();
    }

    /**
     * Run all tests.
     */
    public function run(): void
    {
        echo "=== getCountCdr Optimization Tests ===\n\n";

        // Get date ranges for testing
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        // Run tests
        $this->testDirectQuery('Today only', $today, $today);
        $this->testDirectQuery('Yesterday only', $yesterday, $yesterday);
        $this->testDirectQuery('Last 7 days', $weekAgo, $today);
        $this->testDirectQuery('Current month', $monthStart, $monthEnd);
        $this->testDirectQuery('Last month',
            date('Y-m-01', strtotime('-1 month')),
            date('Y-m-t', strtotime('-1 month'))
        );

        // Summary
        $this->printSummary();
    }

    /**
     * Test direct query and store baseline results.
     */
    private function testDirectQuery(string $name, string $start, string $end): void
    {
        echo "Testing: {$name} ({$start} to {$end})\n";

        $startTime = microtime(true);
        $result = $this->connector->getCountCdr($start, $end, [], [], [], 0, []);
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);

        $this->testResults[$name] = [
            'start' => $start,
            'end' => $end,
            'result' => $result,
            'time_ms' => $elapsed,
        ];

        echo "  cInner:    {$result['cINNER']}\n";
        echo "  cOutgoing: {$result['cOUTGOING']}\n";
        echo "  cIncoming: {$result['cINCOMING']}\n";
        echo "  cMissed:   {$result['cMISSED']}\n";
        echo "  cTotal:    {$result['cCalls']}\n";
        echo "  Time:      {$elapsed} ms\n";
        echo "\n";

        $this->passed++;
    }

    /**
     * Compare first call (populates cache) vs second call (uses cache).
     * Both calls go through getCountCdr which now uses lazy-cache internally.
     */
    public function compareWithCached(): void
    {
        echo "\n=== Comparing First Call vs Second Call (Cached) ===\n\n";

        foreach ($this->testResults as $name => $baseline) {
            echo "Testing: {$name}\n";

            $firstResult = $baseline['result'];
            $firstTime = $baseline['time_ms'];

            // Second call - should use cache for completed days
            $startTime = microtime(true);
            $secondResult = $this->connector->getCountCdr(
                $baseline['start'],
                $baseline['end'],
                [], [], [], 0, []
            );
            $secondTime = round((microtime(true) - $startTime) * 1000, 2);

            // Compare results
            $match = $this->compareResults($firstResult, $secondResult);

            if ($match) {
                echo "  ✓ Results match\n";
                echo "  First call:  {$firstTime} ms\n";
                echo "  Second call: {$secondTime} ms";
                if ($secondTime < $firstTime && $firstTime > 0) {
                    $speedup = round($firstTime / max($secondTime, 0.1), 1);
                    echo " ({$speedup}x faster)";
                }
                echo "\n";
                $this->passed++;
            } else {
                echo "  ✗ Results MISMATCH!\n";
                echo "  First:  " . json_encode($firstResult) . "\n";
                echo "  Second: " . json_encode($secondResult) . "\n";
                $this->failed++;
            }
            echo "\n";
        }
    }

    /**
     * Test that cache is populated correctly.
     */
    public function testCachePopulation(): void
    {
        echo "\n=== Testing Cache Population ===\n\n";

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Check if cache exists for yesterday
        $cached = DailyCallStats::findFirst([
            'conditions' => 'date = :date:',
            'bind' => ['date' => $yesterday]
        ]);

        if ($cached) {
            echo "✓ Cache exists for {$yesterday}\n";
            echo "  cInner:    {$cached->cInner}\n";
            echo "  cOutgoing: {$cached->cOutgoing}\n";
            echo "  cIncoming: {$cached->cIncoming}\n";
            echo "  cMissed:   {$cached->cMissed}\n";
            echo "  cTotal:    {$cached->cTotal}\n";
            echo "  Updated:   {$cached->updatedAt}\n";
            $this->passed++;
        } else {
            echo "⚠ No cache for {$yesterday} (will be created on first query)\n";
        }
    }

    /**
     * Test cache invalidation scenario.
     */
    public function testCacheConsistency(): void
    {
        echo "\n=== Testing Cache Consistency ===\n\n";

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Get direct count
        $direct = $this->connector->getCountCdr($yesterday, $yesterday, [], [], [], 0, []);

        // Get from cache
        $cached = DailyCallStats::findFirst([
            'conditions' => 'date = :date:',
            'bind' => ['date' => $yesterday]
        ]);

        if (!$cached) {
            echo "⚠ No cache to compare\n";
            return;
        }

        $cacheSum = $cached->cInner + $cached->cOutgoing + $cached->cIncoming + $cached->cMissed;
        $directSum = $direct['cINNER'] + $direct['cOUTGOING'] + $direct['cINCOMING'] + $direct['cMISSED'];

        if ($cacheSum === (int)$direct['cCalls'] && $directSum === (int)$direct['cCalls']) {
            echo "✓ Cache is consistent with direct query for {$yesterday}\n";
            $this->passed++;
        } else {
            echo "✗ Cache INCONSISTENT for {$yesterday}\n";
            echo "  Direct cCalls: {$direct['cCalls']}\n";
            echo "  Cache sum:     {$cacheSum}\n";
            $this->failed++;
        }
    }

    /**
     * Compare two result arrays.
     */
    private function compareResults(array $a, array $b): bool
    {
        $keys = ['cINNER', 'cOUTGOING', 'cINCOMING', 'cMISSED', 'cCalls'];
        foreach ($keys as $key) {
            if ((int)($a[$key] ?? 0) !== (int)($b[$key] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Print test summary.
     */
    private function printSummary(): void
    {
        echo "=== Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";

        if ($this->failed === 0) {
            echo "\n✓ All tests passed!\n";
        } else {
            echo "\n✗ Some tests failed!\n";
        }

        // Store baseline for future comparison
        $baselineFile = __DIR__ . '/../db/test_baseline.json';
        file_put_contents($baselineFile, json_encode($this->testResults, JSON_PRETTY_PRINT));
        echo "\nBaseline saved to: {$baselineFile}\n";
    }

    /**
     * Load and compare with saved baseline.
     */
    public function compareWithBaseline(): void
    {
        $baselineFile = __DIR__ . '/../db/test_baseline.json';

        if (!file_exists($baselineFile)) {
            echo "No baseline file found. Run tests first to create baseline.\n";
            return;
        }

        $baseline = json_decode(file_get_contents($baselineFile), true);
        echo "\n=== Comparing with Saved Baseline ===\n\n";

        foreach ($baseline as $name => $data) {
            $current = $this->connector->getCountCdr(
                $data['start'],
                $data['end'],
                [], [], [], 0, []
            );

            $match = $this->compareResults($data['result'], $current);

            if ($match) {
                echo "✓ {$name}: Results match baseline\n";
                $this->passed++;
            } else {
                echo "✗ {$name}: Results DIFFER from baseline\n";
                echo "  Baseline: " . json_encode($data['result']) . "\n";
                echo "  Current:  " . json_encode($current) . "\n";
                $this->failed++;
            }
        }
    }
}

// Run tests
$test = new GetCountCdrTest();

$mode = $argv[1] ?? 'baseline';

switch ($mode) {
    case 'baseline':
        echo "Mode: Create baseline (direct queries)\n\n";
        $test->run();
        break;

    case 'compare':
        echo "Mode: Compare with baseline\n\n";
        $test->run();
        $test->compareWithBaseline();
        break;

    case 'cached':
        echo "Mode: Compare direct vs cached\n\n";
        $test->run();
        $test->compareWithCached();
        break;

    case 'cache-check':
        echo "Mode: Check cache state\n\n";
        $test->testCachePopulation();
        $test->testCacheConsistency();
        break;

    default:
        echo "Usage: php test_getCountCdr.php [baseline|compare|cached|cache-check]\n";
        echo "  baseline   - Run direct queries and save baseline (default)\n";
        echo "  compare    - Compare current results with saved baseline\n";
        echo "  cached     - Compare direct vs cached results\n";
        echo "  cache-check - Check cache population and consistency\n";
}
