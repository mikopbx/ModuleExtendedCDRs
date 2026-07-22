# Atomic CDR Batch Persistence Design

## Goal

Prevent permanent CDR loss when any database write in a synchronization batch fails. A batch may advance the persisted CDR offset only after every required write has committed successfully.

## Scope

This change covers writes performed by `ConnectorDB::syncCdrData()`:

- `cdr_general` inserts and updates;
- queue-history writes required by the same source batch;
- recall/transfer state changes derived from the saved rows;
- creation of an oversized-linkedid quarantine record;
- persistence of the new synchronization offset after the CDR transaction commits.

A background reconciler for oversized calls is outside this change. Quarantine records must remain available for manual analysis and must not be automatically deleted.

## Transaction Boundary

All module CDR tables involved in a batch use the module CDR SQLite connection. `ConnectorDB` opens one transaction on that connection before the first mutation.

Within the transaction it:

1. saves queue-history changes;
2. inserts or updates call-history rows;
3. updates recall/transfer state;
4. saves quarantine metadata for newly oversized linked IDs.

Every model save must be checked for a `false` result as well as exceptions. Batch INSERT helpers must propagate failures instead of logging and continuing.

If any required write fails, the transaction rolls back, the in-memory and persisted offsets remain unchanged, and the worker retries from the same source boundary after the error delay.

After a successful database commit, `ConnectorDB` computes and persists the new offset. Offset persistence failure is treated as a synchronization failure: the CDR rows may already be committed, but replay is safe because `UNIQUEID` processing is idempotent. The next run starts from the old persisted offset and updates existing rows rather than creating duplicates.

## Oversized Calls

An oversized linked ID is not added to the active exclusion cache before its available CDR rows and quarantine record commit successfully.

On a successful commit:

- its available rows are durable;
- its quarantine record is durable;
- it is added to the exclusion cache;
- the offset remains at the prior value for one cycle so the next query can exclude it and process ordinary calls behind it.

On failure, neither the transaction nor the exclusion becomes active. The same linked ID is retried from the unchanged offset.

Quarantine audit records are retained. Automatic pruning/deletion is disabled until a separate reconciler can mark records resolved with an auditable outcome.

## Failure State and Logging

Each batch produces a stable outcome event containing only operational metadata:

- old offset and proposed offset;
- source last ID and lag;
- parsed minimum/maximum ID;
- linked-ID and row counts;
- insert/update counts;
- mode and elapsed time;
- outcome (`committed`, `rolled_back`, `offset_persist_failed`, `quarantined`);
- normalized error category.

Raw phone numbers and raw linked IDs are not included in normal batch events. Exception details remain in the error log, while the health state receives the normalized category and retains the previous successful-progress timestamp.

## Testing

Tests must demonstrate failure before implementation and cover:

1. a later INSERT chunk fails after an earlier chunk succeeded: transaction rolls back and offset remains unchanged;
2. an UPDATE save returns `false`: transaction rolls back and offset remains unchanged;
3. queue-history or state save returns `false`: transaction rolls back;
4. quarantine persistence fails: no exclusion is activated and offset remains unchanged;
5. successful oversized batch: rows and quarantine commit, exclusion activates, offset is held for the next cycle;
6. restart/replay after a failed offset persistence produces no duplicate `UNIQUEID` rows;
7. successful ordinary batch commits and advances the offset.

Local policy tests, PHP syntax checks, and `git diff --check` remain mandatory. Server verification uses a database backup and a controlled offset rewind. A destructive database fault is simulated only against the test server and must be restored immediately.

## Release Criteria

- No code path advances the persisted offset after a failed required write.
- No oversized linked ID is excluded before its rows and quarantine metadata are durable.
- Failed batches are distinguishable from successful empty batches in both log and health state.
- Replaying a committed batch is idempotent.
- Existing history and trunk-resolution tests continue to pass.
