# Isolated Production Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a client-ready ModuleExtendedCDRs release that closes arbitrary recording access, archive command injection, unauthenticated private REST access, and known dependency vulnerabilities without changing CDR synchronization behavior.

**Architecture:** Move path validation and archive construction out of `ApiController` into small pure-PHP services. Use MikoPBX's canonical monitor directory, `realpath()` containment, and `PharData` tar creation; require authentication on every private route and update the PHP-7.4-compatible dependency lock and bundled vendor tree.

**Tech Stack:** PHP 7.4.6, MikoPBX module REST API, `MikoPBX\Core\System\Directories`, `PharData`, Composer 2, existing standalone PHP test harness, SSH test server `serber@boffart.miko.ru`.

## Global Constraints

- Preserve the transactional CDR batch, quarantine, offset, and trunk-resolution contracts already on `develop`.
- Support platform PHP exactly as resolved by Composer's `config.platform.php` value `7.4.6`.
- Require `phpoffice/phpspreadsheet >=1.30.5,<2.0` and `setasign/fpdi >=2.6.7`.
- Do not interpolate request, database, filename, or path values into a shell command.
- Do not log credentials, cookies, authorization headers, complete queries, recording paths, phone numbers, or report contents.
- Keep legacy `view` compatibility only behind the same recording-root validation as `CallRecordID`.
- Do not push or deploy to a client without explicit user authorization.

---

## File Map

- Create `Lib/RecordingPathPolicy.php`: canonical path containment, media validation, MIME lookup, and safe download filename.
- Create `Lib/RecordingPathResult.php`: immutable validation result with reason code, HTTP status, resolved path, MIME type, and filename.
- Create `Lib/RecordingArchiveBuilder.php`: validate inputs, assign safe unique entry names, build a tar with `PharData`, and clean partial output.
- Create `Lib/RecordingArchiveResult.php`: archive path plus accepted/skipped counts.
- Modify `Lib/RestAPI/Controllers/ApiController.php`: delegate recording and archive operations, stream controlled responses, and remove shell use.
- Modify `Lib/ExtendedCDRsConf.php`: require authentication for every private module route.
- Modify `composer.json`, `composer.lock`, and `vendor/`: resolve patched dependencies as one reproducible set.
- Create `tests/RecordingPathPolicyTest.php`, `tests/RecordingArchiveBuilderTest.php`, `tests/PrivateRoutesTest.php`, and `tests/DependencyPolicyTest.php`.
- Modify comments in `Lib/RestAPI/Controllers/ApiController.php`: remove concrete sessions, hosts, and filesystem examples.

---

### Task 1: Canonical Recording Path Policy

**Files:**
- Create: `Lib/RecordingPathResult.php`
- Create: `Lib/RecordingPathPolicy.php`
- Create: `tests/RecordingPathPolicyTest.php`

**Interfaces:**
- Produces: `RecordingPathPolicy::__construct(string $recordingRoot, array $mimeTypes = [])`
- Produces: `RecordingPathPolicy::validate(string $candidate): RecordingPathResult`
- Produces: `RecordingPathResult::{isAllowed(),reason(),status(),path(),mimeType(),downloadName()}`

- [ ] **Step 1: Write the failing path-policy test**

Create a temporary monitor root and sibling directory. Cover valid MP3/WAV/WEBM files, missing file, directory, sibling-prefix path, `../`, an escaping symlink, `phar://`, a NUL byte, and `.txt`. Assertions must include `outside_recording_root`/404 and `unsupported_media_type`/415 without expecting the rejected path in any result field.

```php
$policy = new RecordingPathPolicy($monitorRoot);
$ok = $policy->validate($monitorRoot . '/2026/07/call.mp3');
assertSame(true, $ok->isAllowed(), 'valid recording');
assertSame('audio/mpeg', $ok->mimeType(), 'MP3 MIME');

$escape = $policy->validate($monitorRoot . '-old/secret.mp3');
assertSame(false, $escape->isAllowed(), 'sibling prefix rejected');
assertSame('outside_recording_root', $escape->reason(), 'safe reason');
assertSame(null, $escape->path(), 'rejected path is not retained');
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/RecordingPathPolicyTest.php`

Expected: failure because `RecordingPathPolicy.php` does not exist.

- [ ] **Step 3: Implement the immutable result and policy**

Use `realpath()` for both root and candidate. Reject `\0` and any case-insensitive `^[a-z][a-z0-9+.-]*://` before filesystem calls. Accept only when `is_file($realCandidate)` and:

```php
$allowedBase = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$insideRoot = strpos($realCandidate . DIRECTORY_SEPARATOR, $allowedBase) === 0;
```

Map extensions exactly to `mp3 => audio/mpeg`, `wav => audio/wav`, and `webm => audio/webm`. Return only a sanitized basename from allowed results; rejected results carry no path or candidate value.

- [ ] **Step 4: Run policy and syntax tests**

Run: `php tests/RecordingPathPolicyTest.php && php -l Lib/RecordingPathPolicy.php && php -l Lib/RecordingPathResult.php`

Expected: `RecordingPathPolicyTest: OK` and both files report no syntax errors.

- [ ] **Step 5: Commit the path boundary**

```bash
git add Lib/RecordingPathPolicy.php Lib/RecordingPathResult.php tests/RecordingPathPolicyTest.php
git commit -m "security: restrict recording paths to monitor root"
```

---

### Task 2: Shell-Free Recording Archive Builder

**Files:**
- Create: `Lib/RecordingArchiveResult.php`
- Create: `Lib/RecordingArchiveBuilder.php`
- Create: `tests/RecordingArchiveBuilderTest.php`

**Interfaces:**
- Consumes: `RecordingPathPolicy::validate(string): RecordingPathResult`
- Produces: `RecordingArchiveBuilder::__construct(RecordingPathPolicy $policy, string $tempRoot)`
- Produces: `RecordingArchiveBuilder::build(array $records): RecordingArchiveResult`
- Input record: `array{path:string,name:string}`
- Produces: `RecordingArchiveResult::{path(),acceptedCount(),skippedCount()}`

- [ ] **Step 1: Write the failing archive tests**

Tests must provide names containing `;`, `$()`, backticks, slashes, Unicode, control characters, and duplicates. Open the resulting tar with `PharData` and assert that entries stay flat, source contents match, duplicate names become `call.mp3`, `call-2.mp3`, and no marker command executes. Also test mixed valid/invalid records, zero valid records, and deletion of a partial tar after an injected exception.

```php
$result = $builder->build([
    ['path' => $validOne, 'name' => 'call;touch PWNED'],
    ['path' => $validTwo, 'name' => 'call;touch PWNED'],
]);
assertSame(2, $result->acceptedCount(), 'two files archived');
assertSame(false, file_exists($tempRoot . '/PWNED'), 'no shell execution');
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/RecordingArchiveBuilderTest.php`

Expected: failure because the builder does not exist.

- [ ] **Step 3: Implement deterministic names and tar construction**

Create the temp root with mode `0700`. Generate the tar filename with `bin2hex(random_bytes(16)) . '.tar'`. Normalize entry stems to Unicode letters/numbers plus `._-`, replace other runs with `-`, trim punctuation, fall back to `recording`, cap the stem at 120 bytes without splitting UTF-8, preserve only the validated extension, and add `-2`, `-3`, etc. Use only:

```php
$archive = new \PharData($archivePath);
$archive->addFile($allowed->path(), $entryName);
```

On any exception, unlink the partial archive and rethrow a domain `RuntimeException('archive_build_failed', 0, $previous)`. When no file is accepted, throw `RuntimeException('archive_has_no_valid_entries')`.

- [ ] **Step 4: Run archive, path, and syntax tests**

Run: `php tests/RecordingArchiveBuilderTest.php && php tests/RecordingPathPolicyTest.php && php -l Lib/RecordingArchiveBuilder.php && php -l Lib/RecordingArchiveResult.php`

Expected: both tests report `OK`; syntax checks pass.

- [ ] **Step 5: Commit archive isolation**

```bash
git add Lib/RecordingArchiveBuilder.php Lib/RecordingArchiveResult.php tests/RecordingArchiveBuilderTest.php
git commit -m "security: build recording archives without shell commands"
```

---

### Task 3: Harden Individual Recording Download

**Files:**
- Modify: `Lib/RestAPI/Controllers/ApiController.php:38-87`
- Create: `tests/RecordingResponsePolicyTest.php`

**Interfaces:**
- Consumes: `Directories::getDir(Directories::AST_MONITOR_DIR)`
- Consumes: `RecordingPathPolicy::validate()`
- Keeps: `CallRecordID` and deprecated `view` query parameters

- [ ] **Step 1: Write failing source-level and header-policy tests**

Assert that the controller imports `Directories` and `RecordingPathPolicy`, obtains `AST_MONITOR_DIR`, never calls `file_exists()` on unvalidated request input, and formats a download header through a pure helper that strips CR/LF/quotes and supplies `filename*` encoding for Unicode.

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/RecordingResponsePolicyTest.php`

Expected: failure because the controller still opens the raw `view` value.

- [ ] **Step 3: Delegate lookup and validation**

Resolve `CallRecordID` first; use `view` only when the ID is empty. Construct the policy from `Directories::getDir(Directories::AST_MONITOR_DIR)`. For rejected results, log only `event=recording_rejected reason=<reason> endpoint=records`, return the result status, and do not include the candidate. Open and size only `$result->path()`.

Set `X-Content-Type-Options: nosniff`, the validated MIME type, and a safe attachment header. Always close the file handle in `finally`.

- [ ] **Step 4: Run focused checks**

Run: `php tests/RecordingResponsePolicyTest.php && php tests/RecordingPathPolicyTest.php && php -l Lib/RestAPI/Controllers/ApiController.php`

Expected: all pass.

- [ ] **Step 5: Commit endpoint hardening**

```bash
git add Lib/RestAPI/Controllers/ApiController.php tests/RecordingResponsePolicyTest.php
git commit -m "security: validate recording downloads before streaming"
```

---

### Task 4: Replace Archive Endpoint Shell Pipeline

**Files:**
- Modify: `Lib/RestAPI/Controllers/ApiController.php:218-249`
- Modify: `tests/RecordingResponsePolicyTest.php`

**Interfaces:**
- Consumes: `RecordingArchiveBuilder::build(array): RecordingArchiveResult`
- Produces HTTP tar response and deletes the generated tar in `finally`

- [ ] **Step 1: Extend the failing controller test**

Assert that `downloads()` contains no `shell_exec`, `passthru`, `mkdir`, `ln`, `tar`, `rm -rf`, or interpolated command. Assert it converts report rows to `{path,name}`, delegates to the builder, sets `application/x-tar`, streams a server-generated filename, and unlinks the result in `finally`.

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/RecordingResponsePolicyTest.php`

Expected: failure on the current `shell_exec()`/`passthru()` implementation.

- [ ] **Step 3: Implement the delegated archive response**

Use the configured `core.tempDir . '/ModuleExtendedCDRs/archives'` and canonical monitor root. Skip malformed report rows before calling the builder. Map `archive_has_no_valid_entries` to a controlled 404 and other pre-header build failures to 500. Log endpoint, safe reason, accepted count, and skipped count only. Stream with `fopen`/`fpassthru`, close the handle, and unlink the archive in `finally`.

- [ ] **Step 4: Run endpoint and archive tests**

Run: `php tests/RecordingResponsePolicyTest.php && php tests/RecordingArchiveBuilderTest.php && php -l Lib/RestAPI/Controllers/ApiController.php && rg -n 'shell_exec|passthru|system\(|exec\(' Lib/RestAPI/Controllers/ApiController.php`

Expected: tests pass and `rg` returns no matches.

- [ ] **Step 5: Commit archive endpoint hardening**

```bash
git add Lib/RestAPI/Controllers/ApiController.php tests/RecordingResponsePolicyTest.php
git commit -m "security: isolate recording archive downloads"
```

---

### Task 5: Require Authentication and Remove Sensitive Examples

**Files:**
- Modify: `Lib/ExtendedCDRsConf.php:113-123`
- Modify: `Lib/RestAPI/Controllers/ApiController.php:20-275`
- Create: `tests/PrivateRoutesTest.php`

**Interfaces:**
- Consumes confirmed core contract: route element index `5` is `$noAuth`; `true` bypasses JWT/session authentication.
- Produces: all five private routes with index `5 === false`.

- [ ] **Step 1: Write the failing route test**

Parse the returned route source or instantiate with minimal stubs and assert the `downloads`, `exportHistory`, `exportHistoryDetail`, `recordsAction`, and `exportOutgoingEmployeeCalls` rows all end in `false`. Also scan production PHP for `PHPSESSID=`, `boffart.miko.ru`, `/storage/usbdisk`, bearer/JWT examples, and credential-like query strings.

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/PrivateRoutesTest.php`

Expected: failure because `exportHistory` currently ends in `true` and comments contain concrete session/server values.

- [ ] **Step 3: Close unauthenticated access**

Change only `exportHistory` from `true` to `false`; keep the other four authenticated flags false. Replace concrete curl comments with credential-free localhost examples or remove them. Add a short comment documenting that index 5 means `noAuth`, so `false` is intentional.

- [ ] **Step 4: Run route and secret scans**

Run: `php tests/PrivateRoutesTest.php && rg -n 'PHPSESSID=|boffart\.miko\.ru|Authorization: Bearer|/storage/usbdisk' App Lib bin --glob '*.php'`

Expected: test passes and scan has no production-code matches.

- [ ] **Step 5: Commit authorization policy**

```bash
git add Lib/ExtendedCDRsConf.php Lib/RestAPI/Controllers/ApiController.php tests/PrivateRoutesTest.php
git commit -m "security: require authentication for private CDR routes"
```

---

### Task 6: Upgrade Vulnerable Export Dependencies

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `vendor/`
- Create: `tests/DependencyPolicyTest.php`

**Interfaces:**
- Requires: `phpoffice/phpspreadsheet:^1.30.5`
- Requires transitive/direct floor: `setasign/fpdi:^2.6.7`
- Preserves: `config.platform.php=7.4.6`, `mpdf/mpdf=8.0.4` unless Composer proves incompatibility.

- [ ] **Step 1: Write the failing lock-policy test**

Read `composer.lock`, locate both packages, normalize a leading `v`, and assert `version_compare()` against `1.30.5` and `2.6.7`. Assert `composer.json` still pins platform PHP to `7.4.6`.

- [ ] **Step 2: Run the test and verify RED**

Run: `php tests/DependencyPolicyTest.php`

Expected: failure showing locked PhpSpreadsheet `1.29.2` and FPDI `2.6.1`.

- [ ] **Step 3: Resolve one compatible dependency set**

Install Composer 2 in a temporary tool path if the CLI is absent. Change the direct PhpSpreadsheet constraint to `^1.30.5` and add `setasign/fpdi:^2.6.7` as an explicit security floor. Run:

```bash
composer update phpoffice/phpspreadsheet setasign/fpdi --with-all-dependencies
composer validate --strict
composer audit --locked
```

Expected: resolution succeeds for PHP 7.4.6, validation succeeds, and audit reports no known vulnerabilities. Do not use `--ignore-platform-reqs` and do not edit `vendor` manually.

- [ ] **Step 4: Verify dependency runtime and artifact consistency**

Run `php tests/DependencyPolicyTest.php`, instantiate `Spreadsheet`, write a minimal XLSX to `/tmp`, instantiate `Mpdf`, write a one-line PDF to `/tmp`, then run Composer's installed-package check. Confirm bundled source versions match the lock and cleanup-vendor did not remove runtime files.

Expected: XLSX and PDF files are non-empty; policy test passes.

- [ ] **Step 5: Commit the reproducible dependency update**

```bash
git add composer.json composer.lock vendor tests/DependencyPolicyTest.php
git commit -m "security: update spreadsheet and PDF dependencies"
```

---

### Task 7: Full Local Regression and Security Review

**Files:**
- Modify only files required by findings from this task.

**Interfaces:**
- Consumes all prior tasks.
- Produces a clean local release candidate and review evidence.

- [ ] **Step 1: Run every standalone test**

Run each `tests/*.php` with PHP in sorted order. Expected: every script exits 0 and prints its `OK` marker.

- [ ] **Step 2: Lint every changed PHP file and check whitespace**

Run PHP lint over changed `.php` files, `git diff --check`, and `git status --short`. Expected: no lint/whitespace failure and only intentional files plus pre-existing `.DS_Store` remain.

- [ ] **Step 3: Scan the release diff**

Search for `shell_exec`, `passthru`, unsafe `exec/system`, `phar://` outside tests, cookies/tokens, server hostnames, absolute customer paths, and SQL/error dumps. Inspect every match and remove unsafe production occurrences without weakening negative tests.

- [ ] **Step 4: Request independent code review**

Review against the design release gate: path containment, symlink behavior, archive cleanup, authentication flag semantics, dependency audit, sensitive logging, PHP 7.4 syntax, and unchanged CDR synchronization. Resolve every critical/high finding with a failing regression test first.

- [ ] **Step 5: Commit review corrections**

```bash
git add -u Lib tests composer.json composer.lock vendor
git commit -m "fix: address hardening review findings"
```

Skip the commit only when the review produced no code change.

---

### Task 8: Packaged Upgrade and Adversarial Test-Server Verification

**Files:**
- Modify: release/build metadata only if required by the module's existing packaging workflow.
- Create locally or under `/tmp`: release artifact and verification evidence; do not commit server backups or test recordings.

**Interfaces:**
- Consumes the complete local release candidate.
- Produces a verified installable artifact and go/no-go report.

- [ ] **Step 1: Capture and back up server state**

Over SSH, record module version/hash, current CDR offset, source maximum ID, module row/distinct counts, worker PID/count, asset symlink targets, and relevant log tail. Create a timestamped recoverable backup of module code and its two SQLite databases before installation.

- [ ] **Step 2: Build and inspect the actual module artifact**

Use the repository's existing CI/package workflow. Inspect the archive to prove it contains the new services, patched `composer.lock`, matching vendor versions, public assets, and no `.DS_Store`, test fixtures, credentials, or server-specific files.

- [ ] **Step 3: Install the artifact through the normal module lifecycle**

Install/update on `serber@boffart.miko.ru`, not by overlaying individual files. Verify enable/start succeeds, exactly one ConnectorDB worker runs, and asset symlinks are created automatically. On failure, restore the timestamped backup and stop the release.

- [ ] **Step 4: Execute functional and adversarial smoke tests**

Verify module page/CSS/JS are HTTP 200 with correct MIME types; CDR offset catches up and survives restart; existing synthetic CDRs remain exactly-once; authorized MP3/WAV/WEBM downloads work where fixtures exist; unauthenticated requests fail; outside-root, traversal, symlink, `phar://`, unsupported extension, CRLF filename, and hostile archive-name attempts fail without path leakage or command execution.

- [ ] **Step 5: Verify every report format and diagnostics**

Exercise history, queue, and employee reports in JSON, XLSX, and PDF where the UI exposes them. Confirm non-empty valid output, no fatal/OOM, no new dependency warnings, and security logs contain safe reason codes and aggregate counts only.

- [ ] **Step 6: Verify persistence and cleanup**

Restart the module/PBX service used by the normal lifecycle, recheck the page, worker count, CDR offset, and last structured batch log. Confirm generated archives, temporary recordings, fault fixtures, and synthetic additions created for this hardening test are removed.

- [ ] **Step 7: Produce the release decision**

Report exact commit, artifact checksum, dependency versions/audit result, local test count, server scenarios, before/after offset and row counts, backup location, and remaining findings. Mark client-ready only when no unresolved critical/high issue remains and packaged upgrade plus assets passed. Do not push or deploy to the client until explicitly authorized.
