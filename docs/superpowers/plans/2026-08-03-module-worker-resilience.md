# ModuleExtendedCDRs Worker Resilience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent overlapping ModuleExtendedCDRs watchdog processes, bound their runtime, and make ConnectorDB exit cleanly with useful diagnostics after Beanstalk failures.

**Architecture:** Small pure-PHP policy/resource classes own locking, deadlines, process metrics, and diagnostic payload construction. `safe.php` and `ConnectorDB.php` remain thin infrastructure adapters around MikoPBX Core APIs. The cron entry supplies an outer BusyBox timeout, while the ConnectorDB worker exits after a broken listener connection so the next singleton watchdog run establishes a fresh connection.

**Tech Stack:** PHP 7.4.6, `flock`, optional `pcntl_alarm`, Linux `/proc`, MikoPBX WorkerBase/Processes/BeanstalkClient, standalone PHP regression tests.

## Global Constraints

- Change only files shipped by `ModuleExtendedCDRs`.
- Do not change MikoPBX Core, Redis, nginx, or Monit.
- Preserve the public contract of `ConnectorDB::invoke()`: failures return an empty array.
- Diagnostics must not contain request payloads, phone numbers, linked IDs, or recording paths.
- Missing `pcntl` or `/proc` support must degrade safely.
- Deploy to `serber@boffart.miko.ru` through the existing module-safe installation/update workflow and retain a timestamped rollback backup.

---

### Task 1: Exclusive Module Watchdog Lease

**Files:**
- Create: `Lib/WorkerWatchdogLease.php`
- Create: `tests/WorkerWatchdogLeaseTest.php`

**Interfaces:**
- Produces: `WorkerWatchdogLease::tryAcquire(string $path, int $pid, int $startedAt): ?WorkerWatchdogLease`
- Produces: `WorkerWatchdogLease::release(): void`
- Produces: automatic release from `__destruct()`

- [ ] **Step 1: Write the failing lease test**

Create two real lease attempts against one temporary file. Assert the first succeeds, the second returns `null`, stale pre-existing contents do not prevent acquisition, and acquisition succeeds after release.

```php
$first = WorkerWatchdogLease::tryAcquire($path, 101, 1700000000);
assertLease(true, $first instanceof WorkerWatchdogLease, 'first owner acquires');
assertLease(null, WorkerWatchdogLease::tryAcquire($path, 202, 1700000001), 'contender skips');
$first->release();
assertLease(true, WorkerWatchdogLease::tryAcquire($path, 303, 1700000002) instanceof WorkerWatchdogLease, 'released lease reacquires');
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/WorkerWatchdogLeaseTest.php`
Expected: FAIL because `Lib/WorkerWatchdogLease.php` does not exist.

- [ ] **Step 3: Implement the minimal lease**

Open with `fopen($path, 'c+')`, acquire `LOCK_EX | LOCK_NB`, truncate and write a JSON diagnostic record only after acquisition, keep the resource in the object, and use an idempotent `release()`.

- [ ] **Step 4: Run the test and verify GREEN**

Run: `php tests/WorkerWatchdogLeaseTest.php`
Expected: `WorkerWatchdogLeaseTest: OK`.

- [ ] **Step 5: Commit the lease**

```bash
git add Lib/WorkerWatchdogLease.php tests/WorkerWatchdogLeaseTest.php
git commit -m "feat: serialize module watchdog runs"
```

### Task 2: Watchdog Runtime Policy and Process Diagnostics

**Files:**
- Create: `Lib/WorkerRuntimePolicy.php`
- Create: `Lib/WorkerProcessMetrics.php`
- Create: `tests/WorkerRuntimePolicyTest.php`
- Create: `tests/WorkerProcessMetricsTest.php`

**Interfaces:**
- Produces: `WorkerRuntimePolicy::watchdogDeadlineSeconds(): int`
- Produces: `WorkerRuntimePolicy::outerTimeoutSeconds(): int`
- Produces: `WorkerRuntimePolicy::shouldLogHealth(int $now, int $lastLoggedAt): bool`
- Produces: `WorkerProcessMetrics::collect(int $pid, int $startedAt, string $procRoot = '/proc'): array`

- [ ] **Step 1: Write failing policy and metrics tests**

Assert literal boundaries: a 40-second internal deadline, a 50-second outer timeout, health logging no more often than every 300 seconds, non-negative uptime/memory, counted entries in a synthetic `fd` directory, and unavailable socket/FD fields for a missing proc tree.

- [ ] **Step 2: Run both tests and verify RED**

Run: `php tests/WorkerRuntimePolicyTest.php && php tests/WorkerProcessMetricsTest.php`
Expected: FAIL because both production classes are missing.

- [ ] **Step 3: Implement the pure policies**

Use constants for 40/50/300-second boundaries. Collect `pid`, `uptimeSeconds`, `memoryBytes`, `peakMemoryBytes`, `openFdCount`, and `tcpSocketCount`; count socket symlink targets beginning with `socket:[` and return `null` when proc data cannot be read.

- [ ] **Step 4: Run both tests and verify GREEN**

Run: `php tests/WorkerRuntimePolicyTest.php && php tests/WorkerProcessMetricsTest.php`
Expected: both tests print `OK`.

- [ ] **Step 5: Commit policies and diagnostics**

```bash
git add Lib/WorkerRuntimePolicy.php Lib/WorkerProcessMetrics.php tests/WorkerRuntimePolicyTest.php tests/WorkerProcessMetricsTest.php
git commit -m "feat: add bounded worker diagnostics"
```

### Task 3: Harden safe.php and Its Cron Boundary

**Files:**
- Create: `Lib/WorkerWatchdogRunner.php`
- Create: `Lib/ModuleWatchdogCommand.php`
- Modify: `bin/safe.php`
- Modify: `Lib/ExtendedCDRsConf.php`
- Create: `tests/WorkerWatchdogRunnerTest.php`
- Create: `tests/ModuleCronPolicyTest.php`

**Interfaces:**
- Consumes: `WorkerWatchdogLease`, `WorkerRuntimePolicy`, `WorkerProcessMetrics`
- Produces: `WorkerWatchdogRunner::run(array $workers, callable $findPids, callable $startWorker, callable $signalDuplicates, callable $log): int`
- Produces: `ModuleWatchdogCommand::build(string $busybox, string $php, string $moduleDir, int $timeoutSeconds): string`
- Produces: cron command `busybox timeout 50 php -f <module>/bin/safe.php`

- [ ] **Step 1: Write a failing runner behavior test**

Use real runner logic with local callbacks and assert: no PID starts one worker; one PID starts none; multiple PIDs signal every PID except the final canonical PID; each phase produces a sanitized diagnostic; a thrown callback returns a non-zero status.

- [ ] **Step 2: Write a failing cron behavior test**

Build a command with `ModuleWatchdogCommand::build(..., 1)` around a temporary PHP fixture that blocks for longer than one second. Execute the real command and assert timeout exit status `124` and elapsed time below three seconds. Also assert paths containing spaces are shell-escaped by executing a fixture from such a path.

- [ ] **Step 3: Run both tests and verify RED**

Run: `php tests/WorkerWatchdogRunnerTest.php && php tests/ModuleCronPolicyTest.php`
Expected: FAIL because runner and command builder are missing.

- [ ] **Step 4: Implement the runner and safe.php adapter**

Acquire the lease before any MikoPBX helper call. Install an optional `pcntl_async_signals(true)`/`pcntl_alarm(40)` handler, track the active phase, delegate worker decisions to the runner, catch `Throwable`, log structured events through `SystemMessages::sysLogMsg`, cancel the alarm, release the lease in `finally`, and exit with the runner status.

- [ ] **Step 5: Add the outer cron timeout**

Build the once-per-minute command through `ModuleWatchdogCommand` with the discovered BusyBox and PHP paths and `WorkerRuntimePolicy::outerTimeoutSeconds()`. Preserve output redirection and existing cleanup/report cron tasks.

- [ ] **Step 6: Run tests and syntax checks; verify GREEN**

Run: `php tests/WorkerWatchdogRunnerTest.php && php tests/ModuleCronPolicyTest.php && php -l bin/safe.php && php -l Lib/ExtendedCDRsConf.php`
Expected: tests print `OK`; syntax checks report no errors.

- [ ] **Step 7: Commit watchdog integration**

```bash
git add Lib/WorkerWatchdogRunner.php Lib/ModuleWatchdogCommand.php bin/safe.php Lib/ExtendedCDRsConf.php tests/WorkerWatchdogRunnerTest.php tests/ModuleCronPolicyTest.php
git commit -m "fix: bound module watchdog execution"
```

### Task 4: ConnectorDB Beanstalk Failure Boundary

**Files:**
- Create: `Lib/WorkerFailureContext.php`
- Create: `Lib/TemporaryFileGuard.php`
- Modify: `bin/ConnectorDB.php`
- Create: `tests/WorkerFailureContextTest.php`
- Create: `tests/TemporaryFileGuardTest.php`

**Interfaces:**
- Consumes: `WorkerProcessMetrics`, `WorkerRuntimePolicy`
- Produces: `WorkerFailureContext::make(string $operation, Throwable $error, array $metrics, int $elapsedMs): array`
- Produces: `TemporaryFileGuard::track(string $path): void`, `forget(string $path): void`, and idempotent `cleanup(): void`

- [ ] **Step 1: Write the failing diagnostic-context test**

Assert stable event name `worker_dependency_failure`, operation, exception class, elapsed time, and metrics. Pass secrets in the exception message and assert the context stores only a bounded generic category/message without paths, linked IDs, phone numbers, or request content.

- [ ] **Step 2: Write the failing temporary-file test**

Track two real temporary files, forget one after simulating ownership transfer, call cleanup twice, and assert only the still-owned file is removed.

- [ ] **Step 3: Run both tests and verify RED**

Run: `php tests/WorkerFailureContextTest.php && php tests/TemporaryFileGuardTest.php`
Expected: FAIL because both production classes are missing.

- [ ] **Step 4: Implement context and file guard; verify GREEN**

Run: `php tests/WorkerFailureContextTest.php && php tests/TemporaryFileGuardTest.php`
Expected: both tests print `OK`.

- [ ] **Step 5: Integrate listener recovery**

Wrap Beanstalk construction/subscription/wait in operation timers. On listener-side `Throwable`, log `WorkerFailureContext`, set restart intent, leave the loop, and return from `start()` so WorkerBase shutdown can complete. Emit rate-limited health events from the normal loop.

- [ ] **Step 6: Integrate invoke cleanup without changing its contract**

Track request and response files; forget each only after another component owns or the method has removed it; always call cleanup in `finally`; continue returning `[]` on any failure and log only the sanitized failure context.

- [ ] **Step 7: Run focused tests and syntax checks**

Run: `php tests/WorkerFailureContextTest.php && php tests/TemporaryFileGuardTest.php && php -l bin/ConnectorDB.php`
Expected: tests print `OK`; syntax check reports no errors.

- [ ] **Step 8: Commit ConnectorDB recovery**

```bash
git add Lib/WorkerFailureContext.php Lib/TemporaryFileGuard.php bin/ConnectorDB.php tests/WorkerFailureContextTest.php tests/TemporaryFileGuardTest.php
git commit -m "fix: recover connector after beanstalk failures"
```

### Task 5: Full Verification and Test-Server Deployment

**Files:**
- Modify only if verification exposes a module-owned defect in files listed above.

**Interfaces:**
- Consumes: completed module archive/install workflow and SSH access to `serber@boffart.miko.ru`
- Produces: verified installed module state with rollback evidence.

- [ ] **Step 1: Run the complete local PHP suite**

Run every `tests/*.php` file in sorted order and stop at the first failure. Then run `php -l` for every changed PHP file and `git diff --check`.

- [ ] **Step 2: Capture read-only server baseline**

Over SSH record MikoPBX/PHP/module versions, installed module checksum/status, cron line, PIDs and elapsed times for ConnectorDB/safe.php, Beanstalk availability, recent module/system errors, and an HTTP health response. Do not restart services during baseline.

- [ ] **Step 3: Build and inspect the module package**

Use the repository's existing build/package workflow. Inspect the archive to ensure new runtime classes are included and `.git`, tests, docs, `.DS_Store`, credentials, and local artifacts are excluded.

- [ ] **Step 4: Back up and deploy on the test server**

Create a timestamped backup of the installed module under `/tmp`, install/update using the module-safe workflow, and verify installed file hashes against the package.

- [ ] **Step 5: Exercise singleton and worker recovery**

Start concurrent `safe.php` invocations, verify one owner and successful overlap skips, observe at least two cron intervals, verify exactly one ConnectorDB, invoke an existing harmless ConnectorDB API, and confirm fresh health/phase diagnostics without secrets or log flooding.

- [ ] **Step 6: Verify web health and rollback readiness**

Confirm authenticated web/module endpoints respond, CDR synchronization advances or remains caught up, no stale lock/process appears, and the timestamped backup remains available. Restore the backup and restart only ConnectorDB if any module regression appears.

- [ ] **Step 7: Record final evidence**

Capture local test counts, server PIDs, cron command, relevant structured log events, HTTP status, deployed checksums, and `git status --short` for the final report.
