# Roadmap

Phases run in order. Each one ends with the quality gates green and a tagged release. The detailed task list lives in [Docs/PLAN.md](Docs/PLAN.md); this file is the short public view of where the project is.

## Phase 0 — Foundation ✅

Repo skeleton, tooling, CI, and one thin vertical slice through the module conventions.

- [x] Laravel 13 on PHP 8.3, Filament 5, Livewire 4
- [x] Pint, Larastan level 6, Pest 4, all enforced in GitHub Actions
- [x] ADR-001 on module boundaries
- [x] Walking skeleton: a Product in the Catalog module with its own migration, factory, Filament resource and tests
- [x] AGPL-3.0 license, README, this roadmap
- [x] Sail verified: containers up, migrate and seed run, app serves on PHP 8.3

## Phase 1 — Catalog and distribution ✅

Everything an admin needs to describe the business before an order can exist.

- [x] Products, units of measure, price lists with effective dates, price list assignment by customer and route
- [x] Price resolution: the narrowest list in force wins, customer over route over the house default
- [x] Money value object and ADR-004 — integer minor units, never floats
- [x] Outlets with geolocation, sales reps, routes/beats, visit schedules
- [x] Filament resources for all of it, grouped by module
- [x] Admin, manager and rep roles with a shared back-office policy
- [x] Demo seed data: a real-looking Ethiopian FMCG catalogue, four beats, eighteen outlets

## Phase 2 — Orders and inventory ✅

- [x] Order capture with lines priced from the correct price list, and the product snapshotted onto the line
- [x] Order status workflow with guarded transitions that throw rather than returning false
- [x] Cash and credit payment records — no gateways, by design
- [x] Warehouses, van stock, and an append-only stock ledger where a balance cannot go negative outside an explicit adjustment
- [x] End-of-day reconciliation that reports variances and writes the adjustments on close
- [x] Dashboards for orders by route, stock position and outstanding counts
- [x] ADR-005 on the order lifecycle, ADR-006 on the ledger
- [x] CI runs the suite against MySQL as well as SQLite

## Phase 3 — Rep PWA and offline sync ✅

The centrepiece, and the reason the project exists.

- [x] ADR-002 on the sync strategy, written before the code, and ADR-003 on the id strategy
- [x] Idempotent sync API with cursor-based delta pulls, a pre-resolved per-rep pricebook, and explicit conflict flagging
- [x] Rep PWA: today's route, visit flow, offline order capture, no-sale outcomes, sync status
- [x] Hand-written service worker, IndexedDB storage layer, upload queue with retry and backoff — no sync/offline package
- [x] Price-variance flagging when a captured price disagrees with the pricebook at push time
- [x] CI builds and checks the PWA's frontend on every push

## Phase 4 — Polish and launch (in progress)

- [x] Architecture overview, domain glossary, sync deep dive
- [x] README rewritten as the portfolio page: badges, feature tour, updated status
- [x] CONTRIBUTING.md, CODE_OF_CONDUCT.md, issue and PR templates, CHANGELOG.md
- [x] Production Docker setup for self-hosters (`docker/`, `docker-compose.prod.yml`) — separate from the Sail dev setup. A review caught a real build-blocker (the composer stage failing on a missing `ext-intl`, since `filament/support` needs it and composer's own image doesn't ship it) and several production-readiness gaps (`APP_KEY`/`DB_PASSWORD` unguarded, no trusted-proxy config behind a reverse proxy, a root/www-data file-ownership edge case). All fixed. CI now builds the image on every push — the actual, durable proof this works, rather than a one-off local check.
- [ ] Screenshots — dropped; the browser tooling used to capture them didn't produce a savable file. Not a blocker, just genuinely not done.
- [ ] Live demo with nightly seed reset — deliberately deferred; picking a host and paying for it is a call for whoever's running this project, not something to do unprompted. `docker-compose.prod.yml` is ready whenever that happens.
- [ ] `v1.0.0` — held until the live demo lands, since [SOURCE_OF_TRUTH.md](Docs/SOURCE_OF_TRUTH.md) §9 names it as part of the definition of done.

## v1.1 — closing the sync gaps (in progress)

Landing ahead of its own prerequisite tag: `v1.0.0` isn't cut yet (blocked on the live demo, not code), but this is genuinely post-1.0 scope — ADR-002 §10 named these as deliberately out for v1, not missed. Building them now since the demo is what's actually stalled.

- [x] Back-office conflicts queue — a Filament resource over `sync_conflicts`, showing the rejected payload next to the row it lost to
- [ ] ADR-007: reconciling a route reassigned away from a rep, or a hard-deleted customer, against a device that already cached it — device caches never shrink today
- [ ] ADR-008: is line-level order sync worth building — collides with the read-only-once-synced rule ADR-002 §3 calls load-bearing; a second order already covers the case for free
