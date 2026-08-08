# Progress log

Append-only. One dated entry per work session: what got done, what's next.

## 2026-08-08 — repo bootstrap

- Set up the repo structure: Docs/ with the source of truth, build plan, and memory bank, plus .gitignore and README.
- Published the repo on GitHub.
- No application code yet. Phase 0 not started.

## 2026-08-08 — Phase 0, foundation

- Laravel 13.24 on PHP 8.3, with Filament 5.7, Livewire 4.3, Pest 4.7, Larastan 3.10 and Pint.
- GitHub Actions runs Pint, Larastan level 6 and Pest on every push. Green.
- ADR-001 settled the module layout: `app/Modules/<Module>/`, with each module loading its own migrations, models overriding `newFactory()`, and one `discoverResources()` call per module in the panel.
- Walking skeleton: Product in Catalog, proving all three pieces of wiring end to end.
- Sail assembled by hand from Sail's own stubs, because `sail:install` hangs on stdin on this machine. Pinned to PHP 8.3 to match CI and the lockfile; `sail:install` had written 8.5.
- Two packaging bugs found and fixed: the first scaffold dropped Laravel's nested `.gitignore` files (a fresh clone would have had no writable storage), and `sail:install` stripped the in-memory SQLite test connection out of `phpunit.xml`.

## 2026-08-08 — Phase 1, catalog and distribution

- Money value object and ADR-004: integer minor units with a currency, immutable, refuses to mix currencies. 32 tests.
- Catalog: units of measure, price lists with effective dates, price list items, and assignment to a customer or route. `PriceResolver` picks the narrowest list in force — customer, then route, then the house default — with the newest winning a tie. 13 tests.
- Distribution: sales reps, routes/beats, outlets with geolocation, visit schedules on ISO day numbers.
- Roles as a column on the user, not a package. One `BackOfficePolicy` across every resource: everyone reads, managers write, admins delete. Registered per module because Laravel only auto-discovers policies for `app/Models`.
- Six Filament resources plus three relation managers. Prices are edited as decimals and stored as minor units.
- `ScopeDirectory` in the shared kernel lets Catalog name Distribution's outlets without depending on it — the explicit service interface ADR-001 asks for. Distribution implements it; a null implementation keeps Catalog usable alone.
- Demo dataset is fixed rather than generated: 16 products, 2 price lists, 4 beats, 18 outlets, 23 visit days.
- 100 tests green, Larastan level 6 clean, arch tests enforce the module boundary in both directions.

**Next**: Phase 2, orders and inventory.
