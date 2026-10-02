# Incoming-route DID Trunk Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve incoming and missed calls to the correct SIP account by DID when the provider authorizes by IP and carries no SIP username, by extending the pool of "logins" with DIDs declared in the PBX incoming routing table.

**Architecture:** Reuse the existing shared-host `did_username` mechanism in `TrunkResolver` unchanged. Only extend how the host/username index is populated: in addition to `Sip.username`, add every `IncomingRoutingTable.number` under the host of the provider (`IncomingRoutingTable.provider` → `Sip.uniqid`) it routes to. Candidates are deduplicated by provider id so a login and a route pointing at the same provider never look like competing candidates. Load routes with a single query in `GetReport::prepareCdrData`, alongside the existing provider query.

**Tech Stack:** PHP 7.4-compatible production code, standalone PHP regression tests.

## Global Constraints

- Do not add per-CDR or per-call database queries.
- Reuse the existing line-first resolution and shared-host refinement; do not change `resolve()` logic.
- Route DIDs are indexed under the routed provider's host only; never group empty hosts.
- Deduplicate host+key candidates by provider id (login and route for the same provider are one candidate).
- Keep `source=did_username` for route-driven matches (routes only enlarge the login pool).
- Normalize route numbers by retaining digits only, same as usernames and DIDs.

---

### Task 1: Route-extended resolver and report integration

**Files:**
- Modify: `tests/TrunkResolverTest.php`
- Modify: `Lib/TrunkResolver.php`
- Modify: `Lib/GetReport.php`

**Interfaces:**
- Consumes: provider arrays containing `uniqid`, `description`, `host`, `username`; route arrays containing `provider` and `number`; CDR arrays containing `line` and `did`.
- Produces: the existing `TrunkResolver::resolve(array $record, string $callType): array` contract, with `source=did_username` for route- or login-driven shared-host refinement and `source=line_id` for fallback.

- [x] **Step 1: Add failing route-resolution regression tests**

Assert that an IP-auth incoming call whose providers share a host and carry no username resolves to the provider whose incoming route matches the DID; add the same for call type `3`; assert outgoing retains the line provider; assert a login and route for the same provider stay unambiguous; assert routes to empty-host providers are not grouped; assert the same DID routed to two providers on a shared host falls back to the line.

- [x] **Step 2: Run the focused test and verify RED**

Run: `php tests/TrunkResolverTest.php`

Expected: failure where a route-only IP-auth incoming call returns the line provider instead of the routed provider.

- [x] **Step 3: Accept routes and deduplicate candidates**

Add an optional second constructor argument `iterable $routes = []`. Introduce `addUsernameCandidate($host, $key, $candidate)` that skips a candidate already present for that host+key by provider id, and route both username and route indexing through it.

- [x] **Step 4: Index route DIDs under the routed provider's host**

For each route, resolve its provider via `byId`, skip when the provider is unknown or its host is empty, normalize the route number, and add it to the host/username index. Leave `resolve()` untouched.

- [x] **Step 5: Load routes in the report**

In `GetReport::prepareCdrData`, query `IncomingRoutingTable` for rows with a non-empty provider, build `['provider' => ..., 'number' => ...]` rows, and pass them as the second `TrunkResolver` argument. Do not add per-call lookups.

- [x] **Step 6: Run focused and full tests**

Run: `php tests/TrunkResolverTest.php`

Expected: `TrunkResolverTest: OK`.

Run every standalone PHP test under `tests/`.

Expected: exit code 0 and no failed test.

- [x] **Step 7: Review the diff and commit**

Run: `git diff --check && git diff -- Lib/TrunkResolver.php Lib/GetReport.php tests/TrunkResolverTest.php`

Commit only the three implementation files and this plan with message `fix: resolve IP-auth providers by incoming-route DID`.
