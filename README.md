# DukaFlow

[![CI](https://github.com/imranstein/dukaflow/actions/workflows/ci.yml/badge.svg)](https://github.com/imranstein/dukaflow/actions/workflows/ci.yml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20)
![Filament](https://img.shields.io/badge/Filament-5-fdae4b)

Order management for small FMCG distributors, built in Laravel. The interesting part is offline sync: field reps in emerging markets work in places where mobile data drops constantly, so the rep app is a PWA that keeps working without a connection and syncs when one comes back — hand-built, no sync or offline package anywhere in it.

**Live demo:** not yet — deliberately deferred, see [ROADMAP.md](ROADMAP.md). Everything below runs locally in the meantime, and `docker/` has a production-ready setup for anyone who wants to host it themselves sooner.

## Status

Phases 0 through 3 are done — the back office, the full order-to-stock lifecycle, and the offline rep PWA all work end to end. `v0.4.0-beta` is tagged. Phase 4 (this one: docs, packaging, the live demo) is in progress toward `v1.0.0`. See [ROADMAP.md](ROADMAP.md) for the phase-by-phase breakdown.

## Quickstart

You need PHP 8.3+ and Composer. The default database is SQLite, so there is nothing to install or configure.

```bash
git clone https://github.com/imranstein/dukaflow.git
cd dukaflow
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The rep PWA also needs its frontend built once:

```bash
npm install
npm run build
```

Then open **http://localhost:8000/admin** for the back office, or **http://localhost:8000/rep** for the field app. The seed creates one account per role, all with the password `password`:

| Email | Role | What they can do |
|-------|------|------------------|
| `admin@dukaflow.test` | Administrator | Everything, including deleting records |
| `manager@dukaflow.test` | Manager | Maintain the catalogue and the field data |
| `rep@dukaflow.test` | Sales rep | The back office is read-only; `/rep` is theirs |

To see the offline behaviour for real: log into `/rep`, let the page load once (it needs one online visit to cache itself), then stop `php artisan serve` and keep using the app — the round, an order, a no-sale outcome all still work. Start the server again and hit "Sync now."

### With Docker instead

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The app is on http://localhost:8080. A MySQL 8.4 container comes up alongside it; to actually use it rather than SQLite, uncomment the MySQL block in `.env`. This is the dev setup — for a production-style deployment, see [`docker/`](docker/) and [ROADMAP.md](ROADMAP.md).

## What it does

- **Back office** (Filament): products, price lists with effective dates and precedence rules, outlets, sales reps, routes, visit schedules — a distributor can be fully set up from the UI alone.
- **Orders**: a real lifecycle (draft → submitted → approved → fulfilled, or cancelled), each transition guarded rather than silently failing. Lines snapshot the product's price and details at the moment of sale, so a later catalogue edit never rewrites old paperwork. Cash and credit payments, deliberately nothing more — no payment gateways.
- **Van stock**: an append-only stock ledger — a balance is the sum of every movement, never a column that gets edited. Load stock onto a rep in the morning, sell against it during the day, reconcile the van at night; a reconciliation compares the count against the live ledger and writes the adjustment for whatever doesn't match.
- **The rep PWA**: today's route, a visit flow, offline order capture, no-sale outcomes with a reason, a sync status indicator, a manual sync button. Built from a hand-written service worker, IndexedDB, and Alpine.js — no server round-trip per interaction, since there's frequently no server to reach.
- **Offline sync**: idempotent — resubmitting the same order is a no-op that returns the original result, never a duplicate. Genuine conflicts (the same id reused for different content) are flagged for a human, never silently merged. Prices sync pre-resolved per rep rather than shipping the pricing rules to the device to re-run.
- **Dashboards**: orders by route and rep, stock position, reconciliation variances.

The full design write-up, including the decisions that didn't make the obvious cut, lives in [Docs/](Docs/) — start with [the architecture overview](Docs/architecture.md) or, if sync is what you're here for, [the sync deep dive](Docs/sync-deep-dive.md).

## Why build this

Most tools in this space assume perfect connectivity, and small distributors can't afford the ones that don't. Also, offline sync is a genuinely hard problem, and I wanted to build one by hand rather than pull in a library that hides all the interesting decisions. The design gets written up as it goes — every non-obvious call is an [ADR](Docs/adr/) — so the codebase should be worth reading even if you never run it.

## How it's put together

A modular monolith. Each domain area lives under `app/Modules/` and owns its own migrations, models, Filament resources and policies; none of them import another module's models, and a Pest architecture test keeps it that way. [Docs/architecture.md](Docs/architecture.md) has the full map and a diagram; [ADR-001](Docs/adr/0001-module-boundaries.md) has the original reasoning.

Where modules genuinely need each other, they go through an interface in the shared kernel that speaks only in primitives. Attaching a price list to an outlet is the worked example: Catalog needs to list Distribution's outlets by name and is not allowed to read its models, so Distribution implements `ScopeDirectory` and Catalog depends on the interface.

Money is never a float. Prices are integer minor units inside a `Money` value object, for the reasons in [ADR-004](Docs/adr/0004-money-handling.md).

Stack: Laravel 13, Livewire 4, Filament 5, Tailwind, Alpine. Pest for tests, Larastan at level 6 and Pint for formatting, all three gating CI — Pest runs against SQLite and MySQL both, since the stock ledger's invariants are exactly where the two diverge. [Docs/glossary.md](Docs/glossary.md) has the domain vocabulary if any of the naming is unfamiliar.

## Development

```bash
./vendor/bin/pint          # format
./vendor/bin/phpstan analyse   # static analysis, level 6
./vendor/bin/pest          # tests
```

CI runs all three (Pest on both databases), a frontend build, and a full Docker image build, on every push. They have to be green before a phase is considered done. [CONTRIBUTING.md](CONTRIBUTING.md) has the rest of the working conventions if you're looking to send a change.

## License

AGPL-3.0. See [LICENSE](LICENSE).
