# DukaFlow build plan

Phases run strictly in order. A phase is done only when its acceptance criteria pass and every quality gate in [SOURCE_OF_TRUTH.md](SOURCE_OF_TRUTH.md) section 7 is green. Tag a pre-release at each phase end and keep [memory-bank/progress.md](memory-bank/progress.md) up to date as tasks land.

Before starting any phase: sketch a short plan for that phase alone and check it against the source of truth before writing much code.

---

## Phase 0: foundation (repo skeleton) — tag `v0.1.0-alpha`

- [ ] Laravel (latest stable) app scaffolded, PHP 8.3+
- [ ] Laravel Sail configured for local dev
- [ ] Pint, Larastan (level 6), Pest installed and configured
- [ ] GitHub Actions CI: pint + larastan + pest on every push
- [ ] ADR-001: module boundaries (`app/Modules/` vs `src/Modules/`) written and decided
- [ ] Walking skeleton slice: a `Product` model in Catalog with migration, factory, Filament resource, and one Pest feature test, proving the module conventions end to end
- [ ] README v0 (what/why/status), LICENSE (AGPL-3.0), ROADMAP.md

**Acceptance**: `sail up` then migrate/seed works first try on a clean machine; CI green.

## Phase 1: catalog + distribution — tag `v0.2.0-alpha`

- [ ] Catalog: products, units of measure, price lists with effective dates, customer/route price list assignment
- [ ] Distribution: customers (outlets) with geolocation fields, sales reps, routes/beats, visit schedules
- [ ] Filament resources for all of the above
- [ ] Policies + roles: admin, manager, rep
- [ ] Seeders with realistic demo data (Ethiopian and emerging-market flavored names, plausible SKUs)
- [ ] Pest tests for the domain logic: pricing resolution, effective dates, assignments

**Acceptance**: a seeded install lets an admin fully set up a distributor in the UI; domain logic covered by Pest tests.

## Phase 2: orders + inventory — tag `v0.3.0-beta`

- [ ] ADR-004: money handling (value object)
- [ ] Order capture, back office first; order lines priced from the correct price list
- [ ] Order status workflow: draft, submitted, approved, fulfilled, cancelled, with guarded transitions
- [ ] Simple payment records (cash/credit) on orders, nothing more. This is a hard boundary
- [ ] Warehouses, van stock loading, stock movements as an append-only ledger
- [ ] End-of-day reconciliation with variance reporting
- [ ] Dashboards: orders by route/rep/day, stock position, reconciliation variances
- [ ] Invariant tests: state machine transitions; stock can never go negative without an explicit adjustment record

**Acceptance**: full happy path in the UI from stock load to order to reconciliation; state machine and stock ledger invariants tested directly.

## Phase 3: rep PWA + sync (the centerpiece) — tag `v0.4.0-beta`

- [ ] ADR-002: offline sync strategy, written and reviewed before any sync code
- [ ] ADR-003: ID strategy (client-generated UUIDs/ULIDs)
- [ ] Sync API per source of truth section 5: idempotent submissions, cursor-based delta pulls, conflict flagging, device registry, sync audit log, price list version capture
- [ ] Contract tests: idempotency (resubmit is a no-op returning the original result), delta pulls, conflicts flagged rather than merged
- [ ] PWA rep interface: today's route, customer visit flow, offline order capture, visit outcomes (no sale + reason), sync status indicator, manual sync trigger
- [ ] Service worker + IndexedDB storage layer + upload queue with retry/backoff, built by hand with no sync packages

**Acceptance**: the demo script works with the network disabled mid-flow. A rep captures two orders offline, reconnects, and both sync exactly once; a forced conflict is flagged, not silently merged. All of it covered by automated tests at the API layer.

**Contingency**: if this phase blows its budget badly, ship v1 with the online rep interface plus the full sync design doc, and land offline sync in v1.1. The design doc never gets quietly cut.

## Phase 4: polish + launch readiness — tag `v1.0.0`

- [ ] Docs: architecture overview with a diagram, domain glossary, sync deep dive, screenshots, quickstart
- [ ] README as the portfolio page: badges, live demo link with seeded credentials, feature tour
- [ ] CONTRIBUTING.md, CODE_OF_CONDUCT.md, issue/PR templates, CHANGELOG
- [ ] Production-ish docker-compose for self-hosters
- [ ] Live demo deployment (small VPS, Fly.io, or Railway) with a nightly seed reset
- [ ] Tag v1.0.0

**Acceptance**: a stranger goes from README to running app in under 10 minutes; demo credentials work.
