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

### A requested assess-and-review pass, and what it actually found

Run as a 4-agent Workflow on request: two independent passes checking every doc claim (README, ROADMAP, CHANGELOG, SOURCE_OF_TRUTH §9) against the real repo state, one pass dedicated to reviewing the Docker setup fresh, synthesized into one report. The headline finding was real and would have bitten the first self-hoster to try it: the composer stage never actually builds. `filament/support` requires `ext-intl`; `composer:2`'s own image doesn't ship it; nothing in the Dockerfile installed it or told composer to ignore the check. None of the earlier isolated-stage verification (disk-space-limited, described above) happened to exercise `composer install` against the real lock file in the real composer image, so it went uncaught until this review ran `composer install --dry-run` directly and watched it fail with exactly the predicted error. Fixed with `install-php-extensions intl` in the composer stage — and, since the same disk-space wall blocked a full local re-verification, closed properly this time by adding a fourth CI job that runs `docker build` on every push, on a runner that isn't disk-constrained. It passed on the first push with the fix in.

Three more real production-readiness gaps, all fixed: no trusted-proxy configuration, so Laravel would see plain HTTP from any reverse proxy a self-hoster puts in front and emit `http://` URLs onto an `https://` page (env-driven `TRUSTED_PROXIES`, wired into `bootstrap/app.php`); `APP_KEY`/`APP_URL`/`DB_PASSWORD` unguarded in `docker-compose.prod.yml`, so a missing one silently produced a container that started and 500'd every request (`${VAR:?message}` guards, verified to actually fail with a clear message); a root/www-data file-ownership race in `entrypoint.sh` if a startup command touches `storage/logs` before anything fixes ownership (added a `chown` after the artisan commands, not only at build time).

Also real: several doc-accuracy issues the same review caught by checking claims rather than trusting them — ROADMAP's "verified to build" overstating what had actually been checked, `Docs/PLAN.md` still showing every phase's checkboxes unchecked including three shipped ones, a sync-deep-dive sentence implying a back-office conflicts queue that was never built (only the rep PWA's own badge exists), and — worth remembering for its own sake — a "brick" metaphor repeated near-verbatim across three separate docs, flagged as the single clearest sign of one voice writing all of them in one pass rather than being written over time. Reworded two of the three instances rather than all three, keeping the strongest one.

**Next**: whichever comes first — the live demo, or a decision to tag `v1.0.0` without it.

## 2026-08-13 — v1.1, item 1: the back-office conflicts queue

With `v1.0.0` still untagged pending the live demo, started v1.1 anyway — ADR-002 §10 names this and two other items as deliberately out of v1's scope, not accidental gaps, and the demo is what's actually blocking the tag, not code readiness. Recorded the pre-v1.1 HEAD in `activeContext.md` so `v1.0.0` can still be tagged retroactively at the right commit whenever the demo lands.

First of three: a Filament resource over `sync_conflicts` (Sync → Conflicts), so a manager can see a conflict without phoning the rep or querying the database directly — the gap `Docs/sync-deep-dive.md` had explicitly flagged as unbuilt. `sync_conflicts` gained a `rejected_payload` json column (previously hash-only — provable that content differed, not what); `SyncPushHandler::recordConflict()` now stores it. The view page shows the rejected payload next to the winning `sync_audit_log` row, resolved via the same `(client_id, entity_type)` lookup the push handler itself uses for idempotency — not a new relation, since a conflict's "winner" is already defined that way. A table row action marks a conflict resolved with no edit form, since a conflict is produced by the push handler, never typed in by a person.

Two real snags, both dead ends worth not repeating: Filament's `CodeEntry` (the obvious choice for JSON display) needs `phiki/phiki`, a composer dependency `filament/infolists` doesn't bundle — not caught until the page actually rendered, 500ing with a class-not-found. Swapped for a plain `TextEntry` rendering pre-formatted JSON in a `<pre>`, no new dependency. Then `TextEntry` itself turned out to treat array state as a list to iterate (right for tag/badge lists, wrong for one JSON blob) — `formatStateUsing` was getting called once per leaf scalar of the payload instead of the whole array, surfacing as a type error that read like bad data rather than the wrong state shape it actually was. Fixed by pre-rendering to a string via `->state()` so the value never enters that per-item path. Both written up in `activeContext.md`.

Verified live in the browser: logged in as admin, seeded a conflict + its winning audit row via tinker, confirmed the list shows the rep's name (resolved through `ScopeDirectory`, no cross-module import), the view page renders both payloads side by side, and "mark resolved" flips the icon without an edit form. ADR-002 §2 and the sync-deep-dive updated — both used to say this queue didn't exist. 369 Pest tests (368 passed, 1 pre-existing skip from ADR-006), Larastan level 6, Pint all green.

**Next**: ADR-007 — reconciling a route reassigned away from a rep, or a hard-deleted customer, against a device that already cached it.

## 2026-08-13 — v1.1, item 2: ADR-007, before any of its code

Checked ADR-002 §4's already-accepted gap against the real repo before designing around it, and it grew: `CatalogSyncFeed`'s docblock claims "a product never actually disappears from the feed," but `ProductsTable` and `EditProduct` both offer a real `DeleteAction` and `Product` has no `SoftDeletes` — that claim was only ever true for deactivation. So this ADR covers four entity types, not the three ADR-002 named, and the finding itself became part of the ADR's own context section rather than a silent scope change.

Weighed two designs properly rather than picking the one already favored going in. This codebase already has a mechanism for "a module needs to know when another module's record it references has disappeared" — `ScopeRecordDeleted`, fired on `Customer`/`Route` delete, already consumed by Catalog's own `PurgeAssignmentsForDeletedScope`. Reusing it for Sync looked like the obviously idiomatic move at first. It only covers half the actual problem, though: a route reassignment is an update, not a delete, and nothing fires on that today, so tombstones would mean partially reusing one event and inventing a second one for the other case — two signal sources for what is, from a device's side, the identical symptom (a cached id that's no longer valid). Decided on full id-set reconciliation instead: the server hands back the complete current id set for an entity type and scope, the device prunes anything it has cached that isn't in it. One mechanism, not two, and correct regardless of *how* a row left scope rather than depending on catching the moment it did.

`SyncFeed` gains `idsInScope($entityType, $salesRepId): array`. The pull response carries the set as `valid_ids`, but only on the last page of a cursor walk (`has_more === false`) — sending it on every page of a multi-page pull would be pure waste. Full design and the reasoning behind rejecting tombstones is in `Docs/adr/0007-reconciling-stale-device-caches.md`.

**Next**: implement ADR-007 — `idsInScope()` on each feed, the controller wiring, and `db.js`'s pruning counterpart to `putCatalogRows`.

## 2026-08-13 — v1.1, item 2 continued: implementing ADR-007

`SyncFeed` gained `idsInScope(entityType, salesRepId): array`, implemented by both feeds off the exact same scoping query `pull()` already filters by — `DistributionSyncFeed` picked up a shared `customerIdsFor()` helper along the way, since `visit_schedule`'s scope is derived from it in both `pull()` and `idsInScope()` now. `SyncPullController` attaches the set as `valid_ids` only on the last page of a cursor walk (`has_more === false`) — mid-pagination would prune ids the device hasn't finished catching up on. `db.js` gained `pruneCatalogRows(entityType, validIds)`, called from `sync.js`'s `pullAll()` right after `valid_ids` shows up in a response.

Verified the whole path live, not just with Pest: logged in as the seeded rep, confirmed the route and its five customers were cached in `dukaflow-rep-1`'s IndexedDB, reassigned the route to a different rep via tinker, hit "Sync now," and watched all of it — route, customers, visit schedules — actually leave IndexedDB and "Today's round" drop to 0 stops. That's the ADR's own acceptance bar, not just "the code looks right."

One real tooling snag along the way, worth the note it got in `activeContext.md`: `/rep/login` kept redirecting to Laravel's bare welcome page instead of showing the login form, which read exactly like a broken route. It wasn't — `RepAuthController` and the Filament admin panel share one `web` guard, and the browser session was still authenticated as admin from the conflicts-queue verification earlier in the day. `POST /rep/logout` first fixed it.

Also fixed while here: two stale docblocks that would have kept describing this as an open gap — `CatalogSyncFeed`'s "a product never actually disappears from the feed" and `DistributionSyncFeed`'s "known gap, accepted for v1" paragraph, both rewritten to point at `idsInScope()` instead.

374 Pest tests (373 passed, 1 pre-existing skip), Larastan level 6, Pint, and a clean `npm run build` all green.

**Next**: ADR-008 — is line-level order sync worth building. Per ADR-002 §10 this is the third candidate, but unlike the other two it's explicitly not a build mandate — a synced order is already read-only per ADR-002 §3, and a second offline order already covers the case a rep actually has today. The ADR's job is to weigh that honestly, not assume the answer is yes.
