# Atomic CDR Batch Persistence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guarantee that CDR synchronization never advances its checkpoint after a failed required database write and never activates oversized-call exclusion before durable commit.

**Architecture:** Add a small transaction coordinator with a testable result contract, then route all CDR, queue, state, and quarantine mutations through one shared `CdrDbProvider` transaction. Write failures throw into the coordinator, which rolls back and returns a normalized failure; cache and offset mutations occur only after commit.

**Tech Stack:** PHP 7.4, Phalcon models/database adapter, SQLite, standalone PHP regression tests.

## Global Constraints

- Work in the current `develop` branch and do not push.
- Keep module settings/offset persistence outside the CDR transaction because it uses `module.db`; replay after offset-save failure must remain idempotent.
- Do not delete quarantine audit records automatically.
- Do not log raw phone numbers or raw linked IDs in normal batch events.
- Every production behavior change requires a failing test first.

---

### Task 1: Transaction coordinator

**Files:**
- Create: `Lib/AtomicBatch.php`
- Test: `tests/AtomicBatchTest.php`

**Interfaces:**
- Consumes: a Phalcon-compatible adapter exposing `begin()`, `commit()`, and `rollback()`.
- Produces: `AtomicBatch::run(object $db, callable $operation): array{ok:bool,value:mixed,error:string}`.

- [ ] **Step 1: Write the failing test**

Create a fake adapter that records transaction calls. Assert success calls `begin,commit`; an exception calls `begin,rollback`; false `begin` and false `commit` return `ok=false`; and a failed commit attempts rollback.

- [ ] **Step 2: Run test to verify RED**

Run: `php tests/AtomicBatchTest.php`
Expected: FAIL because `Lib/AtomicBatch.php` does not exist.

- [ ] **Step 3: Implement the coordinator**

Implement `AtomicBatch::run()` so it validates each adapter result, executes the callback once, returns its value after commit, catches `Throwable`, rolls back only when a transaction began, and reports a normalized error without logging payload data.

- [ ] **Step 4: Run test to verify GREEN**

Run: `php tests/AtomicBatchTest.php`
Expected: `AtomicBatchTest: OK`.

- [ ] **Step 5: Commit**

```bash
git add Lib/AtomicBatch.php tests/AtomicBatchTest.php
git commit -m "feat: add atomic CDR batch coordinator"
```

### Task 2: Explicit persistence result and failure propagation

**Files:**
- Create: `Lib/BatchPersistenceResult.php`
- Test: `tests/BatchPersistenceResultTest.php`
- Modify: `bin/ConnectorDB.php:474-568, 788-858`

**Interfaces:**
- Produces: `BatchPersistenceResult::success(int $inserted, int $updated): array` and `BatchPersistenceResult::failure(string $category, string $message=''): array`.
- `batchSaveCallHistory()` returns the explicit result and throws on failed adapter execution or model `save() === false` while inside an atomic operation.

- [ ] **Step 1: Write the failing result-contract test**

Assert success contains `ok=true`, counts, and an empty error; failure contains `ok=false`, a stable category, zero counts, and a diagnostic message.

- [ ] **Step 2: Run test to verify RED**

Run: `php tests/BatchPersistenceResultTest.php`
Expected: FAIL because the result class does not exist.

- [ ] **Step 3: Implement the result class and propagate failures**

Remove catch-and-continue behavior from INSERT/UPDATE loops. Treat adapter `execute() !== true`, queue save false, state save false, and model validation errors as exceptions categorized as `insert_failed`, `update_failed`, `queue_save_failed`, or `state_save_failed`.

- [ ] **Step 4: Run focused and existing tests**

Run: `php tests/BatchPersistenceResultTest.php && for test_file in tests/*Test.php; do php "$test_file" || exit 1; done`
Expected: all tests print `OK`.

- [ ] **Step 5: Commit**

```bash
git add Lib/BatchPersistenceResult.php tests/BatchPersistenceResultTest.php bin/ConnectorDB.php
git commit -m "fix: propagate CDR persistence failures"
```

### Task 3: Make synchronization atomic

**Files:**
- Modify: `bin/ConnectorDB.php:310-585`
- Test: `tests/AtomicBatchTest.php`
- Test: `tests/CheckpointPolicyTest.php`

**Interfaces:**
- Consumes: `AtomicBatch::run()` and the explicit persistence result.
- Produces: no offset or success-health update unless the CDR transaction commits.

- [ ] **Step 1: Add failing transaction/checkpoint scenarios**

Extend tests to assert that an operation failing after an earlier mutation rolls back and that `CheckpointPolicy::nextOffset()` holds the old offset for `saveOk=false`.

- [ ] **Step 2: Run tests to verify RED**

Run: `php tests/AtomicBatchTest.php && php tests/CheckpointPolicyTest.php`
Expected: the new late-failure assertion fails before integration.

- [ ] **Step 3: Wrap all required writes**

Acquire the shared `CdrDbProvider` adapter, run queue/history/state/quarantine mutations inside `AtomicBatch::run()`, and on failure publish `batch_write_failed`, log a structured rollback event, set error delay, retain the old in-memory offset, and return. Compute and persist the next offset only after commit.

- [ ] **Step 4: Verify focused tests and syntax**

Run: `php tests/AtomicBatchTest.php && php tests/CheckpointPolicyTest.php && php -l bin/ConnectorDB.php`
Expected: tests pass and syntax is valid.

- [ ] **Step 5: Commit**

```bash
git add bin/ConnectorDB.php tests/AtomicBatchTest.php tests/CheckpointPolicyTest.php
git commit -m "fix: commit CDR batches atomically"
```

### Task 4: Commit-gated quarantine and retained audit

**Files:**
- Create: `Lib/QuarantineActivation.php`
- Test: `tests/QuarantineActivationTest.php`
- Modify: `bin/ConnectorDB.php:1015-1143`

**Interfaces:**
- Produces: `QuarantineActivation::afterCommit(array $current, array $committed): array` for deterministic cache activation.
- `persistOversizedLinkedIds()` persists metadata only and returns IDs eligible for activation after commit.

- [ ] **Step 1: Write failing cache-activation tests**

Assert failed/uncommitted IDs never enter the exclusion list; committed IDs enter once; existing IDs remain; raw IDs are not required in the result log context.

- [ ] **Step 2: Run test to verify RED**

Run: `php tests/QuarantineActivationTest.php`
Expected: FAIL because the activation class does not exist.

- [ ] **Step 3: Implement commit-gated activation**

Move all `$oversizedCache` and `$oversizedPending` mutations after a successful transaction. Make quarantine save false throw. Disable `pruneOversizedLinkedIds()` deletion; retain records until a separate reconciler marks an auditable resolution.

- [ ] **Step 4: Run focused tests**

Run: `php tests/QuarantineActivationTest.php && php tests/QuarantinePolicyTest.php`
Expected: both pass.

- [ ] **Step 5: Commit**

```bash
git add Lib/QuarantineActivation.php tests/QuarantineActivationTest.php bin/ConnectorDB.php
git commit -m "fix: activate CDR quarantine only after commit"
```

### Task 5: Production-safe batch diagnostics

**Files:**
- Create: `Lib/BatchLogContext.php`
- Test: `tests/BatchLogContextTest.php`
- Modify: `bin/ConnectorDB.php:310-585`

**Interfaces:**
- Produces: `BatchLogContext::make(array $state): array` with stable operational keys and no raw call identifiers.

- [ ] **Step 1: Write the failing log-context test**

Assert the context includes event, offsets, sourceLastId, lag, parsed range, counts, mode, elapsedMs, outcome, and errorCategory; assert phone, linkedid, UNIQUEID, and recording fields are absent.

- [ ] **Step 2: Run test to verify RED**

Run: `php tests/BatchLogContextTest.php`
Expected: FAIL because the context class does not exist.

- [ ] **Step 3: Implement and emit one outcome event per batch**

Emit `cdr_sync_batch` for committed, rolled-back, quarantined, and offset-persist-failed outcomes. Do not refresh `lastSuccessAt` on rollback. Include exception stack separately only for unexpected top-level failures.

- [ ] **Step 4: Run focused tests**

Run: `php tests/BatchLogContextTest.php && php -l bin/ConnectorDB.php`
Expected: pass.

- [ ] **Step 5: Commit**

```bash
git add Lib/BatchLogContext.php tests/BatchLogContextTest.php bin/ConnectorDB.php
git commit -m "feat: add safe CDR batch diagnostics"
```

### Task 6: Full verification and test-server fault exercise

**Files:**
- Modify only if a test exposes a defect in files already listed above.

- [ ] **Step 1: Run the complete local suite**

Run every `tests/*Test.php`, lint all changed PHP files, and run `git diff --check`.
Expected: all tests pass, no syntax errors, no whitespace errors.

- [ ] **Step 2: Back up and deploy changed files to the test server**

Create a timestamped backup under `/tmp`, copy only changed runtime files to `serber@boffart.miko.ru`, verify SHA-256 hashes, and start the worker through `bin/safe.php`.

- [ ] **Step 3: Verify successful replay**

Rewind offset across the three marked `codex-test-*-20260722` rows. Assert source max equals committed offset, total/distinct counts do not grow, and every marker remains exactly once.

- [ ] **Step 4: Exercise a controlled write failure**

With a fresh database backup, induce a reversible SQLite write failure, trigger one batch, and assert the log outcome is `rolled_back`, offset is unchanged, and no partial rows remain. Restore permissions/state immediately, rerun, and assert the same batch commits once.

- [ ] **Step 5: Final review**

Review `origin/develop..HEAD` for data-loss, rollback, logging privacy, and compatibility risks. Do not push.
