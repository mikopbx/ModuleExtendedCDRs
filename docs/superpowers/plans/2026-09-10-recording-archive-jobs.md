# Background recording archives

Approved design: prepare recording TAR in a background CLI worker, deduplicate requests server-side, show progress immediately, reuse ready results, download without a browser Blob.

- [x] Add tested filesystem job store: owner/search identity, exclusive reservation, one worker per job, global build lock, atomic status, progress, failure retry, expiration, input revision and recording manifest validation.
- [x] Integrate GetReport selection and existing recording path/size policy in CLI worker. Keep large jobs outside REST request lifetime. Cache outside the existing five-minute cleanup directory; bounded cache and cron cleanup under locks.
- [x] Add authenticated preparation/status actions and a narrowly scoped ticket-only POST transfer action. Private endpoints retain Core authentication. Two-minute HMAC capability only permits an existing ready file, never arbitrary paths.
- [x] Wire the button to single-flight preparation, status polling, accessible inline state, actionable errors and native browser transfer. Preserve current filters including onlyEmployeeConversations.
- [x] Test concurrent reservation/workers, stale jobs, cache invalidation, expiry, ticket tampering, UI double clicks/errors/retry; regenerate JS and run regressions and isolated server PHP tests (job store, real TAR and controller transfer; no live browser route installation).

Compatibility: PHP 7.4 / Phalcon 4 and modern Core. Legacy downloads endpoint uses same job/cache service. Live large recording export is not used as a smoke test. No automatic commit/push.
