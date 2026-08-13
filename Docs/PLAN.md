# DukaFlow build plan

Phases run strictly in order. A phase is done only when its acceptance criteria pass and every quality gate in [SOURCE_OF_TRUTH.md](SOURCE_OF_TRUTH.md) section 7 is green. Tag a pre-release at each phase end and keep [memory-bank/progress.md](memory-bank/progress.md) up to date as tasks land.

Before starting any phase: sketch a short plan for that phase alone and check it against the source of truth before writing much code.

---

## Phase 0: foundation (repo skeleton) — tag `v0.1.0-alpha` ✅

- [x] Laravel (latest stable) app scaffolded, PHP 8.3+
- [x] Laravel Sail configured for local dev
- [x] Pint, Larastan (level 6), Pest installed and configured
- [x] GitHub Actions CI: pint + larastan + pest on every push
- [x] ADR-001: module boundaries (`app/Modules/` vs `src/Modules/`) written and decided
- [x] Walking skeleton slice: a `Product` model in Catalog with migration, factory, Filament resource, and one Pest feature test, proving the module conventions end to end
- [x] README v0 (what/why/status), LICENSE (AGPL-3.0), ROADMAP.md

**Acceptance**: `sail up` then migrate/seed works first try on a clean machine; CI green.

## Phase 1: catalog + distribution — tag `v0.2.0-alpha` ✅

- [x] Catalog: products, units of measure, price lists with effective dates, customer/route price list assignment
- [x] Distribution: customers (outlets) with geolocation fields, sales reps, routes/beats, visit schedules
- [x] Filament resources for all of the above
- [x] Policies + roles: admin, manager, rep
- [x] Seeders with realistic demo data (Ethiopian and emerging-market flavored names, plausible SKUs)
- [x] Pest tests for the domain logic: pricing resolution, effective dates, assignments

**Acceptance**: a seeded install lets an admin fully set up a distributor in the UI; domain logic covered by Pest tests.

## Phase 2: orders + inventory — tag `v0.3.0-beta` ✅

- [x] ADR-004: money handling (value object)
- [x] Order capture, back office first; order lines priced from the correct price list
- [x] Order status workflow: draft, submitted, approved, fulfilled, cancelled, with guarded transitions
- [x] Simple payment records (cash/credit) on orders, nothing more. This is a hard boundary
- [x] Warehouses, van stock loading, stock movements as an append-only ledger
- [x] End-of-day reconciliation with variance reporting
- [x] Dashboards: orders by route/rep/day, stock position, reconciliation variances
- [x] Invariant tests: state machine transitions; stock can never go negative without an explicit adjustment record

**Acceptance**: full happy path in the UI from stock load to order to reconciliation; state machine and stock ledger invariants tested directly.

## Phase 3: rep PWA + sync (the centerpiece) — tag `v0.4.0-beta` ✅

- [x] ADR-002: offline sync strategy, written and reviewed before any sync code
- [x] ADR-003: ID strategy (client-generated UUIDs/ULIDs)
- [x] Sync API per source of truth section 5: idempotent submissions, cursor-based delta pulls, conflict flagging, device registry, sync audit log, price list version capture
- [x] Contract tests: idempotency (resubmit is a no-op returning the original result), delta pulls, conflicts flagged rather than merged
- [x] PWA rep interface: today's route, customer visit flow, offline order capture, visit outcomes (no sale + reason), sync status indicator, manual sync trigger
- [x] Service worker + IndexedDB storage layer + upload queue with retry/backoff, built by hand with no sync packages

**Acceptance**: the demo script works with the network disabled mid-flow. A rep captures two orders offline, reconnects, and both sync exactly once; a forced conflict is flagged, not silently merged. All of it covered by automated tests at the API layer.

**Contingency**: if this phase blows its budget badly, ship v1 with the online rep interface plus the full sync design doc, and land offline sync in v1.1. The design doc never gets quietly cut.

## Phase 4: polish + launch readiness — tag `v1.0.0` (in progress)

- [x] Docs: architecture overview with a diagram, domain glossary, sync deep dive, quickstart
- [ ] Screenshots — dropped; the browser tooling used to capture them didn't produce a savable file
- [x] README as the portfolio page: badges, feature tour. Live demo link pending — see below
- [x] CONTRIBUTING.md, CODE_OF_CONDUCT.md, issue/PR templates, CHANGELOG
- [x] Production-ish docker-compose for self-hosters, with CI now building the image on every push
- [ ] Live demo deployment (small VPS, Fly.io, or Railway) with a nightly seed reset — deliberately deferred, picking a host and paying for it is not something to do unprompted
- [ ] Tag v1.0.0 — held until the live demo lands, per SOURCE_OF_TRUTH.md §9

**Acceptance**: a stranger goes from README to running app in under 10 minutes; demo credentials work. (True today for the local and Docker paths; the live-demo half of this is what's still open.)

## v1.1: closing the sync gaps named in ADR-002 §10 (in progress)

Not "Phase 5" — this is post-1.0 scope by ADR-002's own words, just built before the `v1.0.0` tag exists, since the tag is blocked on the live demo rather than on code. Same discipline as every phase before it: re-read SOURCE_OF_TRUTH §5 and ADR-002 before touching sync code, sketch each piece before writing it, tests in the same change, quality gates green before commit.

- [x] Back-office conflicts queue: a Filament resource over `sync_conflicts` so a manager can see a conflict happened without phoning the rep. Stores the rejected payload alongside the hash, shown next to the row that won.
- [x] ADR-007 — reconciliation: decided full id-set reconciliation over tombstones — it unifies reassignment and hard-delete under one mechanism instead of two, and doesn't depend on a model event having fired. Scope grew to include `product` too: `CatalogSyncFeed`'s docblock claimed a product never disappears from the feed, which turned out to be true only for deactivation, not the real `DeleteAction` the Products resource already offers.
- [x] Implement ADR-007: `SyncFeed::idsInScope()`, the pull response's `valid_ids` field, and client-side pruning in `db.js`. Verified live: reassigned a route away from a rep, hit "Sync now," watched the customer/route/visit-schedule rows actually disappear from IndexedDB and "Today's round" go to 0 stops.
- [ ] ADR-008 — line-level order sync: judge whether it's worth building at all against the read-only-once-synced rule (ADR-002 §3) and the fact that a second order already covers the case today. "Considered, rejected, revisit when X" is an acceptable outcome for this ADR.

**Acceptance**: each item ships with its own tests and, where it changes a documented contract (ADR-002, the sync deep dive, `DistributionSyncFeed`'s docblock), the docs are updated in the same change — not left to drift the way the review found them drifting before.
