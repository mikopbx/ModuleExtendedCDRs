# Database preservation during module updates

The updated installer renames the complete `db` directory to `<modulesDir>/.ModuleExtendedCDRs-upgrade/db` before Core removes the old module. It includes `module.db`, `cdr.db`, SQLite WAL/SHM and journals. Core retains its asset, configuration and cache handling; its backup contains only a placeholder. The new installer restores the original directory using rename. Both paths must be on the same filesystem.

When updating from the legacy installer, Core can still perform the initial backup copy once. The new installer adopts that legacy backup with rename, eliminating the return copy. Subsequent updates between versions containing this installer avoid both copies. Deploy the complete matching package; never bootstrap only the new uninstaller and then install an old package which cannot restore preserved data. Downgrades to an older installer require explicitly restoring the held database first.

## Schema migration

The installed Core builds its expected model schema in an empty in-memory connection. CDR tables are compared against that schema. Missing trailing columns, missing tables and declared indices are created transactionally. Existing data, table root pages and existing column definitions are retained. Column reorder, type/default/constraint changes, removed fields and conflicting index definitions require an explicit migration and abort installation; history is never silently rebuilt.

After migration the normal Core updater is run with SQL writes blocked. If it attempts a schema rebuild or index change, verification fails. Ordinary module settings continue through the standard Core updater.

## Recovery

Only update with module workers stopped by Core. The installer closes its own CDR and settings connections before preserving the directory. A filesystem lock serializes preservation operations. Interrupted restoration is retryable. Conflicting installed and held database directories are never overwritten automatically.

Failed installation re-preserves restored data before returning to Core, because newer Core removes failed installations. Uninstall failure also preserves data even if Core skips the file-uninstall hook. If preservation itself fails, the installer logs the reason and exits to prevent Core's unconditional `finally` removal. The module may remain disabled or unregistered; inspect the installed and held directories and use a matching package to resume. Never delete the held directory to silence a conflict without verifying which database is authoritative.

After a successful install the held `db` directory is absent. The small lock file and retained package skeleton directories can remain. Data preservation is not an independent backup; arrange ordinary backups separately.

## Verification

Local tests: `php tests/DatabaseUpgradeStorageTest.php`, `php tests/DatabaseUpgradeLifecycleTest.php`, `php tests/AdditiveSqliteMigrationTest.php`.

On PBX: `php tests/integration/CdrSchemaUpgradeTest.php` uses only in-memory databases and restores the original DI connection. Covers fresh install, missing trailing column/index, preserved data/rootpage, retries and normal Core no-op. The query guard is also tested against an incomplete schema and must reject rebuilding it.
