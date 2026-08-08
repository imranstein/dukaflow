# Roadmap

Phases run in order. Each one ends with the quality gates green and a tagged release. The detailed task list lives in [Docs/PLAN.md](Docs/PLAN.md); this file is the short public view of where the project is.

## Phase 0 — Foundation

Repo skeleton, tooling, CI, and one thin vertical slice through the module conventions.

- [x] Laravel 13 on PHP 8.3, Filament 5, Livewire 4
- [x] Pint, Larastan level 6, Pest 4, all enforced in GitHub Actions
- [x] ADR-001 on module boundaries
- [x] Walking skeleton: a Product in the Catalog module with its own migration, factory, Filament resource and tests
- [x] AGPL-3.0 license, README, this roadmap
- [ ] Sail verified from a clean checkout

## Phase 1 — Catalog and distribution

Everything an admin needs to describe the business before an order can exist.

- [ ] Products, units of measure, price lists with effective dates, price list assignment by customer and route
- [ ] Customers (outlets) with geolocation, sales reps, routes/beats, visit schedules
- [ ] Filament resources for all of it
- [ ] Admin, manager and rep roles with policies
- [ ] Demo seed data that looks like a real distributor

## Phase 2 — Orders and inventory

- [ ] Order capture with lines priced from the correct price list
- [ ] Order status workflow with guarded transitions
- [ ] Cash and credit payment records
- [ ] Warehouses, van stock, an append-only stock ledger, end-of-day reconciliation
- [ ] Dashboards for orders, stock position and reconciliation variances

## Phase 3 — Rep PWA and offline sync

The centrepiece, and the reason the project exists.

- [ ] ADR-002 on the sync strategy, written before the code
- [ ] Idempotent sync API with cursor-based delta pulls and explicit conflict flagging
- [ ] Rep PWA: today's route, visit flow, offline order capture, sync status
- [ ] Service worker, local storage layer, upload queue with retry

## Phase 4 — Polish and launch

- [ ] Architecture docs, domain glossary, sync deep dive, screenshots
- [ ] Contribution files, changelog
- [ ] Live demo with nightly seed reset
- [ ] v1.0.0
