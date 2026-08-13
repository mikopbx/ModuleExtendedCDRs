# Shared-host trunk resolution

## Problem

Several SIP provider accounts can use the same host. Asterisk records the
technical `line` of one account for calls received through that shared host,
while the call `did` identifies the actual account. Resolving only by `line`
therefore assigns some incoming calls to the wrong provider.

## Resolution rule

Provider metadata is loaded once with the existing `Sip::find("type='friend'")`
query. `GetReport` passes `uniqid`, `description`, `host`, and `username` to
`TrunkResolver`.

`TrunkResolver` builds these in-memory indexes in its constructor:

- provider by `uniqid`;
- number of providers per normalized non-empty host;
- providers by normalized host and normalized username.

Resolution starts with `line -> uniqid`. If no provider matches `line`, the
technical value remains unresolved; the resolver must not search globally by
DID.

For incoming and missed calls (`typeCall` `2` and `3`), when the provider found
by `line` has a non-empty host shared by multiple provider records, the resolver
uses `did` as a username within that host. One matching account overrides the
technical-line provider. No match or multiple matches retain the provider found
by `line`.

Internal and outgoing calls always retain line-based resolution. Empty hosts
are never treated as a shared-host group.

Hosts are normalized by trimming whitespace and converting to lowercase.
Usernames and DIDs are normalized by retaining digits only.

## Performance

There are no database queries during per-CDR resolution. Provider metadata is
queried once and all resolver operations use hash lookups. Construction is
linear in the number of providers and each resolution is constant-time apart
from the small candidate list for an exact host-and-username key.

## Compatibility and fallback

Existing line-based resolution remains the fallback in every ambiguous or
incomplete-data case. Providers with missing identifiers or descriptions keep
the existing exclusion behavior. Provider IDs returned after a successful
shared-host refinement belong to the DID-matched account.

## Tests

Regression tests will cover:

- a unique DID match overriding a different technical line on a shared host;
- the same behavior for missed calls (`typeCall=3`);
- no override when hosts differ;
- no grouping or override for empty hosts;
- no DID refinement for outgoing calls;
- fallback to the line provider for missing and ambiguous DID matches;
- preservation of existing normalization behavior.
