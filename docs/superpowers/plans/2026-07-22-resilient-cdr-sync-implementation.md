# Resilient CDR Synchronization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сделать синхронизацию CDR самовосстанавливающейся при backlog и проблемных звонках, а определение транков — детерминированным и диагностируемым.

**Architecture:** Чистые классы `SyncPolicy` и `TrunkResolver` отделяют решения от инфраструктуры и тестируются без запущенной MikoPBX. `HistoryParser` возвращает явный статус и метаданные выборки; `ConnectorDB` применяет адаптивный catch-up, фиксирует checkpoint только после успешной записи и использует расширенный карантин для проблемных `linkedid`.

**Tech Stack:** PHP 7.4.6, Phalcon models, SQLite, MikoPBX WorkerBase/BeanstalkClient, самостоятельные PHP regression tests.

## Global Constraints

- Не использовать синтаксис и API новее PHP 7.4.6.
- Не удалять и не пересоздавать существующую историю вызовов.
- Сохранить `cdrOffset` как совместимый устойчивый checkpoint.
- Повторная обработка должна быть идемпотентной.
- Не логировать SIP-секреты и пароли.
- Не выполнять push без отдельного разрешения пользователя.
- Проверка на `serber@boffart.miko.ru` начинается с backup и read-only baseline; рабочий модуль заменяется только контролируемо с возможностью немедленного отката.

---

### Task 1: Pure synchronization policy

**Files:**
- Create: `Lib/SyncPolicy.php`
- Create: `tests/SyncPolicyTest.php`

**Interfaces:**
- Produces: `SyncPolicy::decide(int $offset, int $sourceLastId, bool $requestOk, bool $limitReached): array` returning `lag`, `mode`, `delay`, `batchLinkedIds`.

- [ ] **Step 1: Write failing tests** covering zero lag, normal mode, catch-up entry, catch-up exit hysteresis, request failure, and capped batch size.
- [ ] **Step 2: Run** `php tests/SyncPolicyTest.php`; expect a missing-class failure.
- [ ] **Step 3: Implement** a PHP 7.4-compatible pure policy with constants `CATCH_UP_ENTER_LAG`, `CATCH_UP_EXIT_LAG`, `NORMAL_BATCH_LINKED_IDS`, `CATCH_UP_BATCH_LINKED_IDS`, `NORMAL_DELAY_SECONDS`, and `ERROR_DELAY_SECONDS`.
- [ ] **Step 4: Run** `php tests/SyncPolicyTest.php`; expect `SyncPolicyTest: OK`.
- [ ] **Step 5: Commit** `Lib/SyncPolicy.php` and `tests/SyncPolicyTest.php` with message `feat: add adaptive CDR sync policy`.

### Task 2: Explicit source result metadata

**Files:**
- Modify: `Lib/HistoryParser.php`
- Create: `tests/HistoryBatchResultTest.php`

**Interfaces:**
- Produces: `HistoryParser::getHistoryData(int $offset, array $excludeLinkedIds = [], int $linkedIdLimit = self::LIMIT_CDR): array` with keys `ok`, `data`, `oldOffset`, `newOffset`, `minId`, `maxId`, `rowCount`, `linkedIdCount`, `limitReached`, `error`.
- Consumes: batch size selected by `SyncPolicy`.

- [ ] **Step 1: Extract** a pure metadata finalizer callable by a standalone test without MikoPBX services.
- [ ] **Step 2: Write failing tests** proving that a successful empty result differs from a failed request, and that min/max/count/limit metadata is correct.
- [ ] **Step 3: Run** `php tests/HistoryBatchResultTest.php`; expect failure for missing metadata behavior.
- [ ] **Step 4: Thread request success** from `getCdr()` into `getHistoryData()` and return the complete result contract without treating timeout as empty data.
- [ ] **Step 5: Run** both Task 1 and Task 2 tests; expect `OK`.
- [ ] **Step 6: Commit** with message `fix: expose reliable CDR batch status`.

### Task 3: Durable quarantine model

**Files:**
- Modify: `Models/OversizedLinkedIds.php`
- Modify: `bin/ConnectorDB.php`
- Create: `Lib/QuarantinePolicy.php`
- Create: `tests/QuarantinePolicyTest.php`

**Interfaces:**
- Produces: quarantine fields `minId`, `maxId`, `reason`, `attempts`, `firstFailureAt`, `lastFailureAt`, `nextRetryAt`, `status`.
- Produces: `QuarantinePolicy::nextState(array $current, string $reason, int $now): array`.

- [ ] **Step 1: Write failing pure tests** for initial quarantine, exponential retry delay, cap, resolved state, and manual state after the maximum attempts.
- [ ] **Step 2: Run** `php tests/QuarantinePolicyTest.php`; expect failure.
- [ ] **Step 3: Implement** `QuarantinePolicy` without framework dependencies.
- [ ] **Step 4: Extend** `ensureTableExists()` with additive `PRAGMA table_info`/`ALTER TABLE ADD COLUMN` migration; never drop the table.
- [ ] **Step 5: Update** persistence and cache loading so legacy rows remain `row_limit` quarantines and active statuses alone are excluded.
- [ ] **Step 6: Run** syntax checks and policy tests; expect success.
- [ ] **Step 7: Commit** with message `feat: persist retryable CDR quarantine`.

### Task 4: Transaction-safe checkpoint and catch-up loop

**Files:**
- Modify: `bin/ConnectorDB.php`
- Modify: `Lib/HistoryParser.php`
- Create: `tests/CheckpointPolicyTest.php`

**Interfaces:**
- Consumes: `SyncPolicy::decide()` and the HistoryParser batch contract.
- Produces: one `syncCdrData()` result describing whether a batch committed and the next delay.

- [ ] **Step 1: Write failing tests** for checkpoint retention on source error, save error and unexplained gap; advancement after successful save; and retention for a newly quarantined truncated call.
- [ ] **Step 2: Run** `php tests/CheckpointPolicyTest.php`; expect failure.
- [ ] **Step 3: Extract** a pure checkpoint decision from the existing inline offset arithmetic.
- [ ] **Step 4: Change** `syncCdrData()` to reject `ok=false`, choose batch size through `SyncPolicy`, persist rows before offset, and update `cdrOffset` only after the batch is committed.
- [ ] **Step 5: Change** the worker loop to use the policy delay: zero/short delay in catch-up, normal delay when current, bounded backoff on failure, while checking `needRestart` between batches.
- [ ] **Step 6: Preserve** the current oversized detection but express it as a quarantine reason and do not skip unexplained ID ranges.
- [ ] **Step 7: Run** all standalone tests plus `php -l` on modified files.
- [ ] **Step 8: Commit** with message `fix: make CDR checkpoint catch-up resilient`.

### Task 5: Quarantine reconciler and overlap repair

**Files:**
- Create: `Lib/CdrReconciler.php`
- Modify: `bin/ConnectorDB.php`
- Modify: `Lib/HistoryParser.php`
- Create: `tests/CdrReconcilerTest.php`

**Interfaces:**
- Produces: `CdrReconciler::due(array $records, int $now): array` and retry-result state transitions.
- Consumes: idempotent existing CallHistory insert/update behavior.

- [ ] **Step 1: Write failing tests** for due-record selection, successful resolution, delayed retry and transition to manual review.
- [ ] **Step 2: Run** `php tests/CdrReconcilerTest.php`; expect failure.
- [ ] **Step 3: Implement** pure scheduling behavior and a thin infrastructure method in `ConnectorDB` that processes at most one due quarantine record per normal cycle.
- [ ] **Step 4: Add** a bounded overlap query before `committedOffset`; save through the same upsert path without moving the checkpoint backward.
- [ ] **Step 5: Ensure** a global source/DB failure leaves quarantine attempts unchanged.
- [ ] **Step 6: Run** all tests and syntax checks.
- [ ] **Step 7: Commit** with message `feat: reconcile quarantined CDR calls`.

### Task 6: Deterministic trunk resolver

**Files:**
- Create: `Lib/TrunkResolver.php`
- Modify: `Lib/GetReport.php`
- Create: `tests/TrunkResolverTest.php`

**Interfaces:**
- Produces: `TrunkResolver::__construct(array $providers)` and `resolve(array $record, string $callType): array` returning `name`, `id`, `status`, `source`, `candidates`.
- Consumes provider entries with `uniqid`, `username`, `description` and normalized channel/line evidence.

- [ ] **Step 1: Write failing tests** for exact line ID, inbound unique DID/username, peer fallback, outbound behavior, duplicate username ambiguity, unresolved technical value, and evidence priority.
- [ ] **Step 2: Run** `php tests/TrunkResolverTest.php`; expect failure.
- [ ] **Step 3: Implement** resolver indexes and conservative number normalization.
- [ ] **Step 4: Replace** provider maps in `GetReport::prepareCdrData()` with resolver calls while preserving sticky stronger evidence per `linkedid`.
- [ ] **Step 5: Log** ambiguous results without SIP credentials or full call payloads.
- [ ] **Step 6: Run** resolver and full standalone test suite.
- [ ] **Step 7: Commit** with message `fix: resolve CDR trunks deterministically`.

### Task 7: Sync health API and UI state

**Files:**
- Modify: `bin/ConnectorDB.php`
- Modify: `App/Controllers/ModuleExtendedCDRsController.php`
- Modify: `public/assets/js/src/module-export-records-index.js`
- Regenerate: `public/assets/js/module-export-records-index.js`
- Regenerate: `public/assets/js/module-export-records-index.js.map`
- Create: `tests/SyncStateTest.php`

**Interfaces:**
- Produces cached state keys `offset`, `sourceLastId`, `lag`, `mode`, `lastSuccessAt`, `lastError`, `quarantinePending`.

- [ ] **Step 1: Write failing state serialization tests** for current, catching-up, stale and failed states.
- [ ] **Step 2: Run** `php tests/SyncStateTest.php`; expect failure.
- [ ] **Step 3: Publish** the state after each source check and successful batch; do not overwrite `lastSuccessAt` on failure.
- [ ] **Step 4: Return** the state from `getStateAction()` with stable defaults for upgraded installations.
- [ ] **Step 5: Display** a non-blocking “history is catching up” status while leaving saved rows visible.
- [ ] **Step 6: Rebuild** frontend assets using the repository build command.
- [ ] **Step 7: Run** state tests, JS build and syntax checks.
- [ ] **Step 8: Commit** with message `feat: expose CDR synchronization health`.

### Task 8: Local regression and package verification

**Files:**
- Modify only if failures expose defects in files from Tasks 1–7.

- [ ] **Step 1: Run** every `tests/*Test.php` with the available PHP interpreter.
- [ ] **Step 2: Run** `php -l` for every changed PHP file.
- [ ] **Step 3: Run** the existing module build and verify `git diff --check`.
- [ ] **Step 4: Confirm** unrelated `.DS_Store` files remain untracked and untouched.
- [ ] **Step 5: Record** test commands and exact results in the final handoff; do not push.

### Task 9: Controlled verification on boffart.miko.ru

**Files:**
- Remote backup under a timestamped `/tmp/ModuleExtendedCDRs-production-fix-*` directory.
- Remote installed module: `/storage/usbdisk1/mikopbx/custom_modules/ModuleExtendedCDRs` only after baseline and backup succeed.

- [ ] **Step 1: Read-only baseline** over SSH: MikoPBX/PHP versions, installed module revision/files, worker PID, `cdrOffset`, source last ID, current lag, quarantine schema, free disk and recent ConnectorDB errors.
- [ ] **Step 2: Create** a timestamped backup of the installed module files and `db/cdr.db`; verify the backup before any replacement.
- [ ] **Step 3: Upload** a build artifact to a timestamped `/tmp` directory and run syntax/tests there before activation.
- [ ] **Step 4: Activate** files with the existing module-safe workflow, restart only `ConnectorDB`, and verify a single worker PID.
- [ ] **Step 5: Observe** offset/sourceLastId/lag, batch durations, error count and HTTP `getHistory` responses until lag reaches the normal threshold or a real blocker is identified.
- [ ] **Step 6: Exercise** representative inbound and outbound calls supplied by existing CDR data; compare resolver evidence and displayed trunk without exposing secrets.
- [ ] **Step 7: Restart** `ConnectorDB` once and verify checkpoint continuity, no duplicate rows and resumed catch-up.
- [ ] **Step 8: Roll back** immediately on fatal errors, growing lag, DB write failures, duplicate growth or broken history API; restore files/database from the verified backup.
- [ ] **Step 9: Report** remote evidence and leave the server either on the verified build or fully restored. Do not push repository changes.
