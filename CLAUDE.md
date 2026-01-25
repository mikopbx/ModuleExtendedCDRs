# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## CRITICAL RULES

- **Git commit and push ONLY with explicit user permission.** Never run `git commit` or `git push` without the user explicitly asking for it.

## Project Overview

ModuleExtendedCDRs is a MikoPBX extension module for Extended Call Detail Records management and reporting. It synchronizes CDR data from Asterisk, provides call history views, generates reports (PDF/XLSX/JSON), exports via webhooks, and delivers scheduled reports by email.

- **Language**: PHP 7.4.6+
- **Framework**: Phalcon 4.0 (MVC)
- **Namespace**: `Modules\ModuleExtendedCDRs\`
- **License**: GPL-3.0-or-later
- **Module ID**: `ModuleExtendedCDRs`

## Commands

```bash
# Install PHP dependencies
composer install

# Syntax check
php -l <file.php>

# Run test script (development)
php bin/test.php

# Background workers (run on PBX system)
php bin/ConnectorDB.php    # CDR synchronization daemon
php bin/SyncRecords.php    # Webhook export worker
php bin/report2email.php   # Scheduled email reports
```

## Build & CI

GitHub Actions workflow (`.github/workflows/build.yml`) triggers on push to `master`/`develop` and uses the shared `mikopbx/.github-workflows` reusable workflow for building and publishing releases. Version is auto-incremented from initial version `1.33`.

## Architecture

### Data Flow

```
Asterisk CDR DB → ConnectorDB worker → SQLite (db/cdr.db)
                                            ↓
                              GetReport (formatting/filtering)
                                            ↓
                         API / PDF / XLSX / Webhook / Email
```

### Key Layers

| Layer | Location | Purpose |
|-------|----------|---------|
| Controllers | `App/Controllers/` | Admin panel request handling |
| Views | `App/Views/` | Volt templates for UI |
| Forms | `App/Forms/` | Phalcon form definitions |
| Business Logic | `Lib/` | Report generation, CDR parsing, caching |
| REST API | `Lib/RestAPI/Controllers/` | External API endpoints |
| Models | `Models/` | Phalcon ORM (5 models) |
| Workers | `bin/` | Background daemons (extend `WorkerBase`) |
| Setup | `Setup/` | Module install/uninstall |
| i18n | `Messages/` | 31 language translations |
| Assets | `public/assets/` | JS, CSS, images |

### Key Classes

- **`GetReport`** (`Lib/GetReport.php`) — Central report engine: `history()`, `historyDetail()`, `outgoingEmployeeCalls()`, `exportToPdf()`, `exportToXlsx()`
- **`ConnectorDB`** (`bin/ConnectorDB.php`) — Daemon syncing Asterisk CDR → local SQLite, extends `WorkerBase`
- **`HistoryParser`** (`Lib/HistoryParser.php`) — CDR parsing and call history assembly
- **`CacheManager`** (`Lib/CacheManager.php`) — Redis cache for sync progress tracking
- **`ExtendedCDRsConf`** (`Lib/ExtendedCDRsConf.php`) — REST API route registration and module config
- **`ExtendedCDRsMain`** (`Lib/ExtendedCDRsMain.php`) — Module lifecycle (`checkModuleWorkProperly`, `startAllServices`)
- **`ApiController`** (`Lib/RestAPI/Controllers/ApiController.php`) — REST endpoints for export/download

### Database

- **Module settings**: MikoPBX main DB (table `m_ModuleExtendedCDRs`)
- **CDR data**: SQLite at `db/cdr.db` (managed by `CdrDbProvider`)

### Models

- `ModuleExtendedCDRs` — Module configuration/settings
- `ReportSettings` — Per-user report variant preferences
- `CallHistory` — Call history records
- `ExportRules` — Webhook export rules
- `ExportResults` — Export result tracking

### REST API

Base path: `/pbxcore/api/modules/ModuleExtendedCDRs/`

- `GET /records` — Download call recordings
- `GET /exportHistory` — Export call history (JSON/PDF/XLSX)
- `GET /exportHistoryDetail` — Detailed CDR export
- `GET /exportOutgoingEmployeeCalls` — Employee call reports
- `GET /downloads` — File downloads

### Integration Points

- **MikoPBX Core**: AMI, Beanstalk queues, CDR database, module lifecycle hooks
- **Other Modules**: ModuleUsersUI (RBAC/ACL), ModuleUsersGroups (group filtering)
- **External**: Webhooks to arbitrary URLs via `ExportRules`

## JavaScript Build

Source files are in `public/assets/js/src/`. After modifying them, compiled files must be generated in `public/assets/js/`.

**IMPORTANT:** Only edit files in `src/` directory. Files in `public/assets/js/*.js` are auto-generated.

Build process uses Babel via PHPStorm File Watcher:
- See setup: https://docs.mikopbx.com/mikopbx-development/prepare-ide-tools/mac#phpstorm-setup-babel
- Babel path: `/Users/apor/Developement/MikoPBX/MikoPBXUtils/node_modules/.bin/babel`
- Presets: `airbnb`
- Source maps: enabled

To rebuild manually:
```bash
cd /Users/apor/Developement/MikoPBX/MikoPBXUtils && \
./node_modules/.bin/babel \
  /Volumes/DevDisk/apor/Developement/MikoPBX/Extensions/ModuleExtendedCDRs/public/assets/js/src/module-export-records-index.js \
  --out-file /Volumes/DevDisk/apor/Developement/MikoPBX/Extensions/ModuleExtendedCDRs/public/assets/js/module-export-records-index.js \
  --source-maps \
  --presets airbnb
```

## Conventions

- Module extends `PbxExtensionBase` for lifecycle, implements `CDRConfigInterface` for CDR hooks
- Workers extend `WorkerBase` with ping/heartbeat health checks
- REST routes registered via `getPBXCoreRESTAdditionalRoutes()` in `ExtendedCDRsConf`
- Cache keys prefixed with `ModuleExtendedCDRs_`
- Log files at `/var/log/pbx/ModuleExtendedCDRs/{class}.log` with rotation
- PSR-4 autoloading, root namespace maps to repository root
