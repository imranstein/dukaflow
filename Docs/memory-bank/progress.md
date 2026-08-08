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

## 2026-08-08 — review pass over Phases 0 and 1

Put the finished code through an adversarial review before moving on. What it found, and what it changed:

- **Two price lists starting on the same day tied exactly**, and the winner was then whichever row came back first. Real, and invisible: both answers look plausible. The comparator breaks the tie on id now.
- **`Money::format()` went through a float**, so on large amounts it disagreed with `toDecimal()` — two accessors on the same object reporting different money. It groups digits as a string now. `fromDecimal()` also saturated at `PHP_INT_MAX` instead of refusing an amount it cannot hold.
- **`scopeScheduledOn` promised a round in visit order and returned row order.** The `sequence` column was written everywhere and read nowhere, so a rep would have been sent back and forth across the city with nothing looking wrong.
- **Five places where a duplicate hit the unique index instead of a validation rule**, surfacing as a raw `QueryException` rather than a message on the field.
- **The arch rules could not see migrations at all** — anonymous classes outside the PSR-4 map — so the boundary was unenforced on the first thing ADR-001 says a module owns.
- **`ProductFactory` never set a unit of measure**, so no test had ever exercised the relation or the column that renders it.
- Every ADR link pointed at a lowercase `docs/`, which cannot coexist with `Docs/` on macOS or Windows. The two had silently merged, so the links worked locally and were broken on GitHub.

162 tests now, up from 100. The ones worth having are the ones that would have caught the above: same-day ties, scope against scope_id, round ordering, large-amount formatting, and the demo seed itself.

**Next**: Phase 2, orders and inventory.
