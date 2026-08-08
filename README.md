# DukaFlow

Order management for small FMCG distributors, built in Laravel. The interesting part is offline sync: field reps in emerging markets work in places where mobile data drops constantly, so the rep app is a PWA that keeps working without a connection and syncs when one comes back.

## Status

Planning stage. There's no application code yet. What exists so far is the project brief and build plan in [Docs/](Docs/).

## What it will do

- Back office for the distributor: products, price lists, customers, sales reps, routes, visit schedules.
- Orders with a real lifecycle (draft, submitted, approved, fulfilled, cancelled) and stock movements behind them.
- Van stock: load stock onto a rep in the morning, sell against it during the day, reconcile at the end of the day.
- A rep app that works offline. Orders captured in the field sync later, exactly once. Resubmitting the same order is a no-op, and genuine conflicts get flagged for a human instead of being silently merged.
- Dashboards: orders per route and rep, stock position, reconciliation variances.

## Why build this

Most tools in this space assume perfect connectivity, and small distributors can't afford the ones that don't. Also, offline sync is a genuinely hard problem, and I wanted to build one by hand rather than pull in a library that hides all the interesting decisions. The design gets written up as it goes, so the codebase should be worth reading even if you never run it.

## Stack

Laravel, Livewire 3, Alpine.js, Tailwind, and Filament for the admin. Pest for tests, Larastan and Pint in CI. MySQL first, PostgreSQL compatible, Sail for local dev.

## Roadmap

The full breakdown is in [Docs/PLAN.md](Docs/PLAN.md). Short version:

| Phase | Scope |
|-------|-------|
| 0 | Repo skeleton: CI, tooling, one thin vertical slice to prove the module conventions |
| 1 | Catalog and distribution: products, price lists, customers, routes |
| 2 | Orders and inventory: order workflow, van stock, stock ledger, reconciliation |
| 3 | Rep PWA and offline sync |
| 4 | Docs, live demo, v1.0.0 |

## License

AGPL-3.0. The license file lands with Phase 0.
