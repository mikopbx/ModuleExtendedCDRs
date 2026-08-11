# ModuleExtendedCDRs Worker Resilience Design

## Goal

Prevent overlapping module watchdog runs and make `ConnectorDB` recover cleanly from Beanstalk failures without changing MikoPBX Core, Redis, nginx, or Monit.

## Scope

Only files shipped by `ModuleExtendedCDRs` may change. The work covers the module cron entry, `bin/safe.php`, `bin/ConnectorDB.php`, focused pure-PHP support code, tests, and module-owned logging.

The production-like verification target is the test server `serber@boffart.miko.ru`. Deployment must use the existing module installation/update workflow rather than copying an incomplete source tree over the installed module.

## Watchdog Singleton

`bin/safe.php` will acquire a non-blocking exclusive file lock before it calls MikoPBX process or Beanstalk APIs. The lock file will live in the configured runtime/temp area when available, with a module-specific directory under `/tmp` as a fallback.

If another watchdog invocation owns the lock, the new invocation will log one rate-limited skip message and exit successfully without inspecting or starting workers. The lock contents will include the current PID and start timestamp for diagnostics, but lock ownership will be determined by the operating-system lock rather than trusting PID-file contents.

The file handle will remain open for the whole watchdog run. Normal return, exceptions, and PHP shutdown will release the lock automatically. Stale file contents do not block startup when no process owns the OS lock.

## Bounded Watchdog Execution

The watchdog will set a module-owned execution deadline. On platforms with `pcntl`, an alarm will terminate an overlong watchdog run after logging its phase and elapsed time. On platforms without `pcntl`, the singleton still prevents overlap and elapsed-time diagnostics identify the blocking phase; no unsafe asynchronous signal emulation will be added.

Worker discovery, duplicate cleanup, and worker startup will be logged as named phases with PID, elapsed milliseconds, and outcome. Existing MikoPBX process helpers remain the source of truth for worker detection and startup.

The cron schedule remains once per minute. The cron command will also use the available BusyBox `timeout` command as an outer process boundary so a PHP call blocked below userland cannot accumulate indefinitely. The timeout must exceed the internal deadline and must not kill a normally completing run.

## ConnectorDB Beanstalk Recovery

`ConnectorDB` will treat Beanstalk construction, subscription, wait, publish, and request failures as recoverable worker-process failures. It will log a structured event containing operation, exception class, message, PID, worker uptime, memory usage, open-file-descriptor count when `/proc` is available, and elapsed milliseconds.

Failures in the main listener connection will cause the worker process to leave its loop and exit. The next singleton-protected watchdog run will start a fresh process and therefore a fresh Beanstalk connection. The worker will not run an unbounded reconnect loop inside a process with potentially corrupted connection state.

Synchronous `invoke()` keeps its existing external contract: failures return an empty array. It will clean request/response temporary files in all paths and emit a bounded diagnostic without exposing request data or recording paths.

## Diagnostics

Module-owned logs will provide structured events for:

- watchdog acquisition, overlap skip, timeout, completion, and phase duration;
- detected ConnectorDB PIDs and duplicate cleanup;
- ConnectorDB start, shutdown, Beanstalk failure, and periodic health;
- process uptime, RSS/memory, open file descriptors, and TCP socket count where supported.

Health diagnostics will be rate-limited to avoid turning a degraded system into a logging storm. Missing `/proc` data will be represented as unavailable and will never terminate a worker.

## Error Handling

- Lock contention is an expected successful no-op.
- A watchdog exception is logged and returns a non-zero exit status after releasing the lock.
- An internal or outer deadline terminates only that watchdog invocation; it does not send signals to a healthy ConnectorDB.
- Existing duplicate ConnectorDB cleanup remains, but it executes only from the lock owner.
- A listener-side Beanstalk error shuts down ConnectorDB cleanly so the watchdog can restart it.
- Diagnostic failures are swallowed after a compact warning and cannot become a worker failure.

## Tests

Pure-PHP tests will cover lock contention, stale lock contents, lock release, diagnostic collection without `/proc`, rate limiting, Beanstalk failure classification, and temporary-file cleanup policy. Script-level tests will verify that cron configuration contains both the timeout boundary and the module watchdog command.

All existing repository PHP tests and syntax checks for changed PHP files must pass.

## Test-Server Verification

Before deployment, capture a read-only baseline on `serber@boffart.miko.ru`: installed module version/files, MikoPBX and PHP versions, cron entry, ConnectorDB and `safe.php` processes, Beanstalk status, recent module/system errors, and web/API health.

After deploying through the module-safe workflow:

1. Trigger several concurrent `safe.php` invocations and verify only one owns the lock.
2. Verify exactly one ConnectorDB worker remains after watchdog convergence.
3. Verify normal CDR synchronization continues and the worker answers its existing API calls.
4. Exercise a controlled Beanstalk interruption only if it can be isolated safely on the test server; otherwise validate recovery by restarting only the ConnectorDB worker and observing fresh subscription/health events.
5. Verify the web interface and authenticated module endpoints remain available.
6. Observe at least two cron intervals for duplicate processes, stale locks, error loops, and excessive log volume.

Any failed activation or regression requires restoration of the timestamped installed-module backup and restart of only the affected module worker.

## Non-Goals

- Changing Redis or nginx behavior.
- Changing Monit policies.
- Modifying MikoPBX Core classes such as `WorkerSafeScriptsCore`, `Processes`, or `BeanstalkClient`.
- Adding a general-purpose process supervisor to the module.
- Destructive Beanstalk or Redis fault injection on a server carrying non-test traffic.
