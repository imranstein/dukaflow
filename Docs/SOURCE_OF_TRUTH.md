# DukaFlow: source of truth

This is the project's canonical document. When any other doc, plan, or conversation disagrees with it, this file wins. Change it deliberately, not casually.

## 1. What this is

DukaFlow is an open source, offline-first field sales and order management app for FMCG-style distribution in emerging markets, built in Laravel. Distributors manage products, price lists, sales reps, routes, and customers from a back office. Reps capture orders in the field on connections that drop constantly, and everything syncs when the network comes back.

It's written for three kinds of readers: distributors who might actually run it, developers and students who want a real Laravel codebase to study or fork, and anyone evaluating the quality of the work. The offline sync layer is the hard part and the main event. Everything else supports it.

## 2. Goals and non-goals

### Goals (v1)

- Distributor back office: products, price lists, customers, sales reps, routes/beats, visit schedules.
- Order lifecycle: draft, submitted, approved, fulfilled, cancelled, with stock movements.
- Van stock: load stock onto a rep, record sales against it, reconcile at end of day.
- Offline-capable rep interface (PWA): browse the catalog, capture orders and visit outcomes offline, sync later.
- A documented, idempotent sync API with explicit conflict resolution rules.
- Dashboards: orders per route and rep, stock position, reconciliation variances.
- Realistic seeders and demo data, one-command local setup, a deployable live demo.
- Docs good enough to learn from: architecture overview, ADRs, domain glossary.

### Non-goals (v1)

Deliberately not building these, even when they seem adjacent:

- No payment gateway integrations of any kind. Payments are simple cash/credit ledger entries against an order. This is a hard boundary.
- No native mobile apps. The rep interface is a PWA.
- No multi-country tax engines. A single configurable tax rate is enough.
- No analytics or forecasting.
- No multi-tenancy in v1. One distributor org per install. Model it so tenancy could be added later, but don't build it.
- No realtime websocket features.

## 3. Stack

- Laravel (latest stable), PHP 8.3+
- Livewire 3 + Alpine.js + Tailwind CSS
- Filament (latest stable) for the back office admin
- Blade + Livewire PWA for the rep interface (service worker, IndexedDB via a small JS layer, background sync queue)
- MySQL primary target, PostgreSQL compatible; SQLite in-memory for tests
- Laravel Sail for local dev, plus a production-ish docker-compose for self-hosters
- Pest for tests, Larastan (level 6 target), Laravel Pint, GitHub Actions CI
- License: AGPL-3.0

## 4. Architecture

Modular monolith. Modules live under `app/Modules/` or `src/Modules/`; decide in ADR-001 and stick to it.

- Catalog: products, units of measure, price lists, price list assignments.
- Distribution: distributor org settings, sales reps, customers (outlets), routes/beats, visit schedules.
- Orders: orders, order lines, order status workflow, simple payment records (cash/credit only).
- Inventory: warehouses, van stock, stock movements, end-of-day reconciliation.
- Sync: sync API endpoints, client device registry, idempotency handling, conflict resolution, sync audit log.

Rules:

- Modules talk to each other through explicit service interfaces or domain events. They never reach into another module's Eloquent models directly.
- Each module owns its migrations, models, Filament resources, Livewire components, policies, and tests.
- The shared kernel stays minimal: base classes, a money value object, ULID/UUID helpers.
- Every decision that took real thought gets an ADR in `docs/adr/`. Minimum required: ADR-001 module boundaries, ADR-002 offline sync strategy, ADR-003 ID strategy (client-generated UUIDs/ULIDs), ADR-004 money handling.

## 5. Offline sync design

The high-level contract. ADR-002 spells out the details before any sync code is written.

- The client generates UUIDs/ULIDs for all offline-created records (orders, visit outcomes). The server never renumbers.
- All sync submissions are idempotent. Resubmitting the same client UUID is a no-op that returns the original result. No duplicate orders, ever.
- Pull sync is delta based: the client sends a cursor or timestamp, the server returns changes since. Catalog and customer data flow down; orders and visit outcomes flow up.
- The conflict policy is explicit and documented per entity type. Default: server wins for master data (catalog, prices), client wins for facts captured in the field (orders, visit notes). True conflicts, like an order edited on both sides, are flagged for human review, not silently merged. No blind last-write-wins.
- Every sync exchange is written to a sync audit log so problems can actually be debugged.
- Price integrity: an offline order records the price list version it was created under. The server validates against it and flags variances instead of silently repricing.

## 6. Phases

Work strictly in order. Each phase ends with all quality gates green and a tagged pre-release. [PLAN.md](PLAN.md) has the task-level breakdown.

- Phase 0: foundation (repo skeleton)
- Phase 1: catalog + distribution
- Phase 2: orders + inventory
- Phase 3: rep PWA + sync (the hard part; protect time for it)
- Phase 4: polish + launch readiness

Contingency: if Phase 3 blows its budget badly, ship v1 with the online rep interface plus the full sync design doc, and land offline sync in v1.1. Sync perfectionism must not block the launch, and the design doc must not get quietly cut either.

## 7. Quality gates (blocking on every phase)

- Pest suite green. Domain logic (pricing, order transitions, stock ledger, sync idempotency, conflict rules) has direct tests, not just HTTP smoke tests.
- Larastan level 6 and Pint clean, both enforced in CI.
- No module boundary violations, meaning no cross-module model imports.
- Factories and seeders updated alongside every new entity.
- Conventional commits, small and focused. No giant squashed "initial commit" dumps. The history is part of the portfolio.
- Every schema or architecture decision that took real thought gets an ADR.

## 8. Working conventions

- Before each phase, write a short plan for that phase only and check it against this document before generating much code.
- Boring, idiomatic Laravel over clever abstractions. Readability outranks brevity, because people should be able to learn from this code.
- No packages beyond the stack in section 3 without a written reason. In particular, no sync or offline packages. The sync layer is built and documented by hand, because that is the point of the project.
- Nothing from the non-goals list gets scaffolded. If a task seems to need one, stop and reconsider the task.
- Tests ship in the same change as the feature, never as a follow-up batch.
- Before touching the sync layer, reread section 5 and ADR-002.
- Seeders stay demo-ready at all times. The demo dataset is a feature.

## 9. Definition of done for v1.0.0

- All Phase 0 through 4 acceptance criteria met.
- Live demo online with resettable seed data.
- Docs complete: README, architecture overview, ADRs 001-004, glossary, sync deep dive, contribution files.
- CI green, Larastan level 6, meaningful Pest coverage of all domain invariants.
- Tagged v1.0.0 with a CHANGELOG and the AGPL-3.0 license file in place.
