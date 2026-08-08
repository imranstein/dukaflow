# DukaFlow

Order management for small FMCG distributors, built in Laravel. The interesting part is offline sync: field reps in emerging markets work in places where mobile data drops constantly, so the rep app is a PWA that keeps working without a connection and syncs when one comes back.

## Status

Phases 0 and 1 are done. The back office runs: you can set up a distributor's catalogue, price lists, outlets, routes, reps and visit schedules, all from the UI. Orders, van stock and the offline rep app are next. See [ROADMAP.md](ROADMAP.md).

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

Then open http://localhost:8000/admin and sign in. The seed creates one account per role, all with the password `password`:

| Email | Role | What they can do |
|-------|------|------------------|
| `admin@dukaflow.test` | Administrator | Everything, including deleting records |
| `manager@dukaflow.test` | Manager | Maintain the catalogue and the field data |
| `rep@dukaflow.test` | Sales rep | Read only; the field app is theirs |

### With Docker instead

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

The app is on http://localhost:8080. A MySQL 8.4 container comes up alongside it; to actually use it rather than SQLite, uncomment the MySQL block in `.env`.

## What it will do

- Back office for the distributor: products, price lists, customers, sales reps, routes, visit schedules.
- Orders with a real lifecycle (draft, submitted, approved, fulfilled, cancelled) and stock movements behind them.
- Van stock: load stock onto a rep in the morning, sell against it during the day, reconcile at the end of the day.
- A rep app that works offline. Orders captured in the field sync later, exactly once. Resubmitting the same order is a no-op, and genuine conflicts get flagged for a human instead of being silently merged.
- Dashboards: orders per route and rep, stock position, reconciliation variances.

## Why build this

Most tools in this space assume perfect connectivity, and small distributors can't afford the ones that don't. Also, offline sync is a genuinely hard problem, and I wanted to build one by hand rather than pull in a library that hides all the interesting decisions. The design gets written up as it goes, so the codebase should be worth reading even if you never run it.

## How it's put together

A modular monolith. Each domain area lives under `app/Modules/` and owns its own migrations, models, Filament resources and policies; none of them import another module's models, and a Pest architecture test keeps it that way. The reasoning is in [Docs/adr/0001-module-boundaries.md](Docs/adr/0001-module-boundaries.md).

Where modules genuinely need each other, they go through an interface in the shared kernel that speaks only in primitives. Attaching a price list to an outlet is the worked example: Catalog needs to list Distribution's outlets by name and is not allowed to read its models, so Distribution implements `ScopeDirectory` and Catalog depends on the interface.

Money is never a float. Prices are integer minor units inside a `Money` value object, for the reasons in [Docs/adr/0004-money-handling.md](Docs/adr/0004-money-handling.md).

Stack: Laravel 13, Livewire 4, Filament 5, Tailwind, Alpine. Pest for tests, Larastan at level 6 and Pint for formatting, all three gating CI. MySQL is the production target, SQLite is the default for local work and in-memory SQLite runs the test suite.

## Development

```bash
./vendor/bin/pint          # format
./vendor/bin/phpstan analyse   # static analysis, level 6
./vendor/bin/pest          # tests
```

CI runs all three on every push. They have to be green before a phase is considered done.

## License

AGPL-3.0. See [LICENSE](LICENSE).
