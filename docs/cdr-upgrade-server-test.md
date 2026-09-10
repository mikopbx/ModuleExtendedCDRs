# Server installation verification — 2026-09-10

Target: `172.16.32.90`. Installed complete development package `1.43.1` from legacy `1.43`, then reinstalled the same package through Core ModuleInstallationBase and WorkerModuleInstaller. The CLI runner used Core installation mutex and lifecycle outside the short-lived REST queue; browser upload/queue timeout behavior was not tested. Module was disabled before and remains disabled.

Package SHA-256: `081dcd12091d71f7e23d408721d16322924aa80eb2ee60a420d294c3faa394c4`.

- First installation: 97.23 seconds, including legacy Core backup copy; success, empty installation_error.
- Repeat installation: 15.43 seconds; success, empty installation_error.
- Database size before and after both installations: 1,449,238,528 bytes.
- Database inode: 821876 before legacy copy, 786691 after first installation, 786691 after repeat.
- Core Backup after repeat contained only zero-byte folder4db. No held database remained after restore.
- Live Core comparison after installation and repeat made no schema/data changes (0.032 / 0.028 seconds).

| Table | Rows before and after | Root page before and after |
|---|---:|---:|
| cdr_general | 2,827,308 | 2 |
| cdr_queue | 28,033 | 4 |
| daily_call_stats | 212 | 327152 |
| oversized_linkedids | 0 | 353812 |

Table SQL definitions, minimum/maximum IDs and SHA-256 hashes of the first/last ten rows matched across all three snapshots. These are targeted preservation checks, not a full-file checksum or SQLite integrity_check.

The live schema already contained all expected columns. Actual missing trailing column/index addition was tested separately in isolated in-memory databases using the installed Core and model classes: fresh creation, append, preserved row/rootpage, retry and Core no-op passed. No columns were removed from the live database for testing.

Server evidence and scripts: `/storage/usbdisk1/mikopbx/tmp/cdr-upgrade-deploy/`, including `before.json`, `after-first.json`, `after-repeat.json`, `install-first.log`, `install-repeat.log`, and `schema-integration.log`.
