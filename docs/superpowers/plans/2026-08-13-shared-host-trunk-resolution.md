# Shared-host Trunk Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve incoming and missed calls to the correct SIP account by DID when the technical line belongs to a host shared by several providers.

**Architecture:** Keep the existing single provider query in `GetReport`, include `host` in each provider row, and build immutable hash indexes in `TrunkResolver`. Resolution remains line-first, with an O(1) host-and-DID refinement only for inbound call types and shared non-empty hosts.

**Tech Stack:** PHP 7.4-compatible production code, standalone PHP regression tests.

## Global Constraints

- Do not add per-CDR or per-call database queries.
- Treat call types `2` and `3` as inbound for shared-host refinement.
- Never group empty hosts.
- Retain the line provider when DID refinement is unavailable or ambiguous.
- Normalize hosts with trim plus lowercase and numbers by retaining digits only.

---

### Task 1: Shared-host resolver and report integration

**Files:**
- Modify: `tests/TrunkResolverTest.php`
- Modify: `Lib/TrunkResolver.php`
- Modify: `Lib/GetReport.php`

**Interfaces:**
- Consumes: provider arrays containing `uniqid`, `description`, `host`, and `username`; CDR arrays containing `line` and `did`.
- Produces: the existing `TrunkResolver::resolve(array $record, string $callType): array` contract, with `source=did_username` for successful shared-host refinement and `source=line_id` for fallback.

- [x] **Step 1: Add failing shared-host regression tests**

Extend provider fixtures with `host`. Assert that an incoming call whose line is Provider A and whose DID matches Provider B resolves to Provider B only when both share the same non-empty host. Add the same assertion for call type `3`. Assert fallback to Provider A for different hosts, empty hosts, outgoing calls, missing DID, and duplicate host-and-username candidates.

- [x] **Step 2: Run the focused test and verify RED**

Run: `php tests/TrunkResolverTest.php`

Expected: failure where a shared-host incoming call returns Provider A instead of Provider B.

- [x] **Step 3: Build constructor hash indexes**

In `TrunkResolver`, retain provider host in `byId`, count providers by normalized non-empty host, and build candidates indexed by normalized host then normalized username. Add a PHP 7.4-compatible host normalization helper using `strtolower(trim($host))`.

- [x] **Step 4: Implement line-first shared-host refinement**

In `resolve`, first look up `line`. If found, refine only when call type is `incoming`, `2`, or `3`, the selected provider has a non-empty host with count greater than one, and exactly one candidate matches that host plus normalized DID. Otherwise return the selected line provider. If line is unresolved, retain the existing unresolved technical result without a global DID lookup.

- [x] **Step 5: Pass provider host from the existing query result**

Add `'host' => $provider->host` to the provider row built by `GetReport::prepareCdrData`. Do not add another model lookup.

- [x] **Step 6: Run focused and full tests**

Run: `php tests/TrunkResolverTest.php`

Expected: `TrunkResolverTest: OK`.

Run every standalone PHP test under `tests/` using the repository's existing test loop.

Expected: exit code 0 and no failed test.

- [x] **Step 7: Review the diff and commit**

Run: `git diff --check && git diff -- Lib/TrunkResolver.php Lib/GetReport.php tests/TrunkResolverTest.php`

Commit only the three implementation files and this plan with message `fix: resolve providers sharing a SIP host`.
