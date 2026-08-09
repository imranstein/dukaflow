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
