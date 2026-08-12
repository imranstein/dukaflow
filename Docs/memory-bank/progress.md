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

## 2026-08-09 — Phase 2, orders and inventory

- **Prerequisite first**: MySQL joined the CI matrix. The suite had only ever run on SQLite while the source of truth names MySQL the production target, and the ledger's invariants are transaction and constraint behaviour — exactly where the two diverge.
- **Cross-module contracts generalised.** Phase 1 bound a single `ScopeDirectory`, which worked while only Distribution had anything to offer. Orders needs to name products *and* outlets, so it became a composite that modules register into. Pricing got the same treatment: Orders prices through a `Pricebook` contract in the shared kernel, implemented by Catalog. Four modules now, none naming another.
- **Orders**: a guarded state machine (draft, submitted, approved, fulfilled, cancelled) that throws rather than returning false. An order line snapshots the product's sku, name and unit alongside the price, because an order is a record of an agreement rather than a view of today's catalogue. Cash and credit payments, no gateways.
- **Inventory**: stock is not a number, it is the sum of every movement. Append-only, enforced in the model. The invariant — a balance may not go below zero outside an explicit adjustment — is checked inside a transaction with the rows locked. Reconciliation reports variances and writes the adjustments on close, each traceable back to the count.
- Fulfilling an order runs in one transaction with its listeners, so a van that turns out short throws and the order stays approved rather than claiming to be fulfilled with no stock behind it.
- ADR-005 (order lifecycle) and ADR-006 (stock ledger).

### What the review pass found

The first review workflow died on a session limit with only one of five reviewers finished. That one reviewer, reading code alone, found six real defects — all of them the same shape: a guard in the right place watching the wrong thing.

- The Filament order form exposed `status` and `total_minor` as free fields. A draft with no lines could be saved as fulfilled: no guard, no timestamp, no event, no stock movement. The stock ledger screen had the same hole and had already been closed the same way.
- `changeQuantity()` and `removeLine()` never checked the line belonged to the order passed in, so the editability guard could be satisfied by a draft while a frozen order lost a line.
- An order opened in `'etb'` rejected every ETB price as a currency mismatch.
- `fulfil()` left the instance marked fulfilled after its transaction rolled back; `cancel()` set the reason before checking whether it could cancel.
- Order references sorted as strings, which reissues a used number at the hundred-thousandth order of a year.

Every one is now pinned by a test named after the case.

**Lesson for next time**: constrain review agents to read-only. The Phase 1 review mutated production code to test whether the suite would catch it — it deleted a `where()` clause from the price resolver and injected a method into an enum — and left nine scratch test files behind, one of which reached a commit. The Phase 2 review ran with an explicit read-only contract and the `Explore` agent type, and left nothing behind while still finding six real bugs by reading alone.

**Next**: Phase 3, the rep PWA and offline sync.

## 2026-08-12 — Phase 3, the rep PWA and offline sync

- **ADR-002 and ADR-003 first**, as the project's own rules require. ADR-002 settles idempotency (the audit log doubles as the idempotency store), the deliberately narrow conflict surface (an order is read-only from the device once synced, so the only conflict is an id reused for different content), the delta-pull cursor and its deletion story, pre-resolved per-rep pricing instead of shipping resolver rules to a device, and session-cookie auth over a new API guard. ADR-003 settles the id question: `orders`/`visit_outcomes` keep their bigint primary keys and gain a nullable `client_id` ULID column, rather than rekeying two phases of working code.
- **`App\Modules\Sync`**: idempotent push for orders and visit outcomes, cursor-based delta pull, a per-rep pre-resolved pricebook, price-variance flagging when a captured price disagrees with the pricebook at push time. Two new shared-kernel contracts, `OrderIntake` and `VisitOutcomeIntake`, so Sync never touches Orders' or Distribution's models directly — same shape as `Pricebook` and `ScopeDirectory` before it.
- **The PWA at `/rep`**: its own login separate from the back office's, a hand-written service worker, an IndexedDB layer, and an Alpine.js capture flow that makes zero server round-trips per interaction — today's route, a visit, an order or a no-sale outcome, all working entirely off what was last pulled. An upload queue drains on reconnect with retry and backoff. No sync or offline package anywhere in it, per the stack rule.
- Verified by hand in the browser, not just by the suite: logged in as a rep, watched the round pull from the server, captured an order and a no-sale outcome, watched them sync, confirmed a repeat sync didn't duplicate anything.
- CI now builds and checks the frontend on every push — the first phase with real frontend tooling in it.

### What the review pass found

Twenty-one candidate findings across five review dimensions, all twenty-one independently confirmed by a second, skeptical pass reading the code fresh. Two were the same defect found twice from different angles, so nineteen distinct fixes:

- **The big one**: the sync pull endpoint answered every rep with every customer, route and visit schedule in the whole distributor, not just their own round. The delta-cursor logic was correct; the scoping was simply never applied. Fixed by threading the resolved rep id into the `SyncFeed` contract itself, so an unscoped call is no longer possible to write by accident.
- **A duplicate-order risk**: the entity write and the audit-log row that makes a push replayable were two separate statements, not one transaction. A crash between them left an order that existed with no way to replay its id — the device's retry would hit the order's own uniqueness constraint and fail forever, which is exactly the kind of stuck submission that pushes a rep to re-key the sale under a new id. Now one transaction.
- **A shared-device bug**: the offline queue lived in one fixed IndexedDB database per browser, not per rep. A company tablet handed from one rep to another mid-shift would silently push the first rep's still-queued sale under the second rep's identity on next sync. Fixed by namespacing the database per rep id — confirmed in the browser with two logins on one session.
- A device could push an order for any customer id, not just one on its own rep's route — closed with an ownership check backed by a new `RepDirectory::ownsCustomer()`.
- A malformed entity's JSON encoding failure crashed the whole push batch instead of failing just that one entity; a malformed pull cursor 500'd instead of a clean 422; a first-contact device-registration race could do the same. All three now fail in isolation.
- `has_price_variance` was computed correctly and never shown anywhere — a real safety net with no human on the other end of it. Now a badge and a filter on the orders table.
- `addCapturedLine` skipped the active-product check `addLine` enforces; a product deactivated between a device's last pull and its next push went through silently. Folded into the same variance flag rather than hard-rejecting, since rejecting a sale that already happened is what causes duplicate re-keying in the first place.
- A no-sale's reason-required rule lived only in a named constructor; a model-level guard now makes it impossible to bypass by any creation path.
- Two gaps in the test suite: nothing proved the price-variance check compared against the order's `placed_at` rather than the current moment, and nothing proved two distinct offline orders in one reconnect both survive as two separate rows — the phase's own acceptance line. Both are tests now.
- One finding was read and deliberately left alone: a device can silently reassign to a different rep on login, which is a device-tracking detail with no bearing on authorization (every push and pull already resolves the acting rep from the session, never from the device row) — worth knowing, not worth the added complexity of refusing it.

Tagged `v0.4.0-beta`.

**Next**: Phase 4, polish and launch readiness.

## 2026-08-12 — Phase 4, polish and launch readiness

- **Docs**: an architecture overview with a diagram of the module/shared-kernel shape, a domain glossary, and a sync deep dive — the last one narrative rather than a decision record, with a sequence diagram of a full pull-then-push cycle, meant to be the one document that explains the whole offline story without reading six ADRs first.
- **README rewritten** as the actual portfolio page: badges, a feature tour in prose (screenshot capture through the browser tool turned out not to persist an actual image file to disk, only an inline view — noted rather than faked, and not worth chasing further given the docs already describe what things look like in enough detail), an updated status section, a quickstart that now also covers the frontend build the rep PWA needs.
- **CONTRIBUTING.md, CODE_OF_CONDUCT.md** (Contributor Covenant 2.1), issue and PR templates, **CHANGELOG.md** reconstructed from the tag history.
- **Production Docker setup**: `docker/Dockerfile` (three stages — composer, the frontend build, then a combined nginx + php-fpm runtime image), `docker/nginx.conf`, `docker/entrypoint.sh` (migrate, never migrate:fresh — this runs against a self-hoster's real data), `docker-compose.prod.yml`, `docker/README.md`. Deliberately not the same as `compose.yaml` (Sail), which is dev-only and bind-mounts the repo.

### What actually verifying it found

A full multi-stage build hit a disk-space wall on the machine this ran on — unrelated to the Dockerfile, confirmed by checking host disk space directly. Rather than ship an unverified Dockerfile on the strength of that, each stage got checked in isolation instead: the frontend build stage runs and produces the right output (confirmed byte-identical asset names to a normal local build), and the runtime stage's package installation was run standalone, which is what actually caught a real bug — `apk del icu-dev` (the standard "install dev headers, build the extension, delete the dev headers" pattern) silently removes `icu-libs` along with it on this base image, since nothing else references it, and `intl` fails to load at runtime with a missing `libicuio.so`. The common pattern doesn't hold universally; testing it beat trusting it. Fixed by re-adding `icu-libs` by name after the delete. Also removed two apk packages (`libzip-dev`, `oniguruma-dev`) and one `docker-php-ext-install` call (`opcache`) that turned out to be unnecessary — checked against the base image's actual bundled extension list rather than assumed.

### The live demo

Asked directly: Fly.io, Railway, an existing VPS, or skip for now. The answer was to skip it — correctly a call for a human, since it means an account, possibly a domain, possibly money, none of which gets decided or spent unprompted. Everything else in SOURCE_OF_TRUTH §9's definition of done for `v1.0.0` is met; the live demo is the one line item still open, so `v1.0.0` stays untagged until it lands. `docker-compose.prod.yml` is sitting ready for whenever that happens.

**Next**: whichever comes first — the live demo, or a decision to tag `v1.0.0` without it.
