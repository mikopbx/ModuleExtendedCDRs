# CDR upgrade without copying history

**Goal:** Preserve the existing SQLite history across module updates and add missing trailing columns without rebuilding populated tables.

**Architecture:** Module-owned file preservation outside the directory deleted by Core; delegate asset/settings installation to Core. Obtain a canonical schema by running the installed Core schema generator against an empty scratch connection, then apply append-only differences to the real CDR connection. Verify the normal Core updater performs no schema/data writes afterwards.

**Constraints:** PHP 7.4+, Phalcon 4/5; no Core patches; preserve SQLite sidecars and interrupted operations; never overwrite two distinct databases; no automatic commit/push. First update from a legacy installer may copy the backup once; deploy the complete matching package, never bootstrap only the uninstaller. Non-additive schema differences fail explicitly instead of copying history silently.

- [x] Verify Core lifecycle, schema metadata and existing schema on 172.16.32.90.
- [x] Add filesystem preservation tests: inode unchanged, retries, conflicting files, SQLite WAL, failure recovery.
- [x] Implement storage and installer integration, delegating standard Core housekeeping.
- [x] Add SQLite schema tests: trailing fields/defaults, preserved rows/rootpage, repeated execution, rejected incompatible changes, missing indices.
- [x] Implement schema migration from Core-generated scratch schema and guard against Core rebuild.
- [x] Run local tests and PBX integration in isolated temporary databases; verify normal Core updater is a no-op after migration.
- [x] Install the complete package on 172.16.32.90 and repeat installation; verify live schema, unchanged row counts/sample hashes/table rootpages, and unchanged database inode on repeat. See docs/cdr-upgrade-server-test.md.
