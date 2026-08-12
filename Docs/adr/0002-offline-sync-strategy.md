# ADR-002: offline sync strategy

- Status: accepted
- Date: 2026-08-12

## Context

A rep's day runs on a connection that comes and goes. The PWA has to let them work through it anyway: see today's route, capture an order or a no-sale outcome at each stop, and have all of it land safely once the phone finds signal again — without ever creating the same order twice, and without ever quietly overwriting something that needs a human to look at it.

Source of truth §5 already states the contract in outline. This ADR is where each piece of it becomes a specific, buildable decision, and where the traps a delta-sync design usually hits — the ones that don't show up until a device has been offline for hours — get settled before any of the Sync module exists.

## Decision

### 1. What moves, and which way

Two kinds of data cross the wire, in opposite directions, for different reasons:

- **Down, on pull**: catalog, customers, routes, visit schedules, and a pre-resolved pricebook. Written in the back office, read on the device.
- **Up, on push**: orders and visit outcomes. Captured on the device, written to the server.

Nothing flows both ways. An order created offline is never edited offline after it's synced — see §3. This asymmetry is what keeps the rest of the design simple, and it is deliberate, not an oversight.

### 2. Idempotency: the audit log *is* the idempotency store

Every sync exchange is already required to be logged (§5). Rather than building a separate idempotency table next to it, the audit log row is the idempotency record. A push of an order writes one row keyed on `(device_id, client_id, entity_type)`, carrying a hash of the payload and the response that was returned for it.

That gives the push endpoint one rule, not two:

- Same `client_id`, same payload hash → the row already exists; return the stored response and write nothing new. A network drop after the server saved the order but before the phone saw the response now costs nothing — the retry the phone sends on its own is a no-op that hands back the original result, which is exactly what "resubmitting the same client UUID is a no-op" (§5) means in practice.
- Same `client_id`, *different* payload hash → the device is asserting a different order under an id it already used once. That can't be a silent overwrite (§5 forbids blind last-write-wins), so it becomes a conflict record instead: nothing is written to the order, the mismatch is logged, and it surfaces wherever the sync status UI shows problems.

`client_id` is the ULID from ADR-003; the uniqueness constraint on it is the backstop that makes this true even if the application logic above it has a bug.

The entity write and the audit log row that makes it replayable happen in one transaction, not two statements in sequence. A strict review found the first implementation wrote them separately: a crash in the gap between them left an order that existed with no audit row to match it, so a retry of the same `client_id` hit the entity's own uniqueness constraint instead of finding a row to replay against — a permanently stuck submission, and the shape of thing that pushes a rep to just re-key the sale under a new id, which is the exact duplicate this design exists to prevent. One transaction closes the gap; a race between two requests for a genuinely new id is resolved after the fact by re-checking the audit log rather than trusting the first exception caught.

### 3. The conflict surface is deliberately narrow

An order or visit outcome, once it has synced, becomes read-only from the device's side. Any correction after that point — the wrong item, a wrong quantity — happens in the back office, through the same guarded Order actions Phase 2 already built. Nothing on the device ever re-submits a change to an order that already exists server-side.

This one rule is what keeps "conflict" a small, well-defined case instead of a general merge problem. The only conflict this design has to handle is the payload-hash mismatch in §2 — an id reused for different content — and that is flagged for a human, never merged. There is no two-sided edit to reconcile because a second edit from the device was never a legal request in the first place.

### 4. Pull: cursor, deletions, and what "delta" means here

**Cursor.** A bare `updated_at` timestamp misses rows: two records updated in the same second land on either side of a `>` depending on how the clock rounded, and one of them silently never reaches the device. The pull cursor is the pair `(updated_at, id)` — "everything after this timestamp, plus, at that exact timestamp, everything with a higher id" — which has no gap for same-second writes to fall through.

**Deletions.** A delta pull can't see a row that no longer exists to be selected. Every entity that flows down already has, or gets, an `is_active` flag rather than a real delete path reachable from the sync contract: deactivating *is* the deletion, as far as a device is concerned, and a deactivated row still comes down on the next pull so the device can drop it locally. `Customer` keeps the hard-delete it already has for back-office cleanup (a duplicate entered twice the same morning, say) and the `ScopeRecordDeleted` event that announces it — that stays an internal, same-day admin operation. It is not something the sync protocol promises to reconcile against a device that has been offline for a week; a customer a manager actually needs to remove from every device gets deactivated, not deleted. Documented here as a deliberate, narrow gap rather than a silent one.

**What pulls, specifically:** products, price lists' resolved effect (see §5, not the lists themselves), customers, routes, visit schedules. Each entity's "changes since" query is exposed by its owning module through a shared-kernel contract described in §7, the same shape as `ScopeDirectory`.

**Scope, not just delta.** Customers, routes and visit schedules are additionally scoped to the requesting rep — a device pulls its own book, not the whole distributor's outlet list. (A strict review of the first implementation found this had been built delta-correct but not scope-correct: every rep's pull returned every rep's customers. Fixed by threading the resolved rep id into the feed contract itself, so an unscoped call is a compile-time impossibility, not a discipline every call site has to remember.) The same shape of gap as the deletion one above follows from it: a route reassigned away from a rep simply stops appearing in that rep's future pulls, rather than arriving once more with an explicit "no longer yours" signal, so a device that already cached it locally keeps a stale copy until told otherwise by some other means. Accepted for the same reason and at the same scale.

### 5. Pricing travels pre-resolved, not as rules to re-run

The pull does not ship price lists and assignments for the device to run `PriceResolver`'s precedence logic in JavaScript. That logic was the most-corrected code in Phase 1; a second implementation of it, in a different language, on a device that can't be patched mid-route, is a bug waiting to happen and a maintenance burden with no upside.

Instead the pull hands the device a flat table: for every product the rep's route can sell, `(product_id, unit_price_minor, price_list_id)`, already resolved server-side for that rep's customers. At this application's scale — a route of under twenty outlets, a catalog of a few dozen products — that's a small, cheap payload, and the device's job on capture shrinks to a lookup.

Price integrity then works the way §5 asks without inventing new machinery: the device echoes back the `price_list_id` and `unit_price_minor` it used when it pushes the order. The server re-resolves the same lookup at push time and compares. Agreement: the order prices as captured. Disagreement — the price list changed under the rep's feet while they were offline — flags a variance on the order for review rather than silently repricing it to whatever is current now. (`price_list_id` alone would not be enough to detect this: a list's items can change without the list itself being superseded, which is exactly why the unit price is what gets compared, not just the list's identity.)

### 6. Timing

The device stamps `placed_at` (orders) or `occurred_at` (visit outcomes) from its own clock, which may be hours behind the server by the time the push arrives — that's the whole point of offline capture. The server additionally stamps `received_at` when the row lands. Pricing and the price-list-version check in §5 are evaluated against `placed_at`, because that's when the sale actually happened; `received_at` exists purely so a large gap between the two is visible in the audit log rather than silently lost. A large gap is logged, not rejected — a phone's clock being wrong doesn't make the sale not have happened.

### 7. Module boundary: two new shared-kernel contracts

Sync writing an order means calling into Orders' `OrderWriter`, and Sync reading "what changed since" from four other modules means calling into each of them. Both are exactly the cross-module calls ADR-001 requires to go through an interface, not an import:

- **`OrderIntake`** (shared kernel, implemented by Orders): the one entry point Sync uses to turn a pushed payload into an order — start the draft, add each line, submit it — so Sync never touches `Order` or `OrderWriter` directly.
- **`SyncFeed`** (shared kernel, one per module that has something to pull): `changesSince(cursor): iterable`, returning primitives. Distribution, Catalog and Orders each register their feed into a `CompositeSyncFeed`, the same registration pattern `CompositeScopeDirectory` already established in Phase 1. Sync depends on the composite and never names another module.

`ModuleBoundaryTest`'s table-owner map gets the new `Sync` module and its tables in the same commit that creates them — the test is doing its job if it fails before that, not misbehaving.

A third contract followed once the design was actually exercised: **`RepDirectory`**, answering "which rep is this user" and "does this rep cover this customer." Every pushed order and visit outcome checks the second question before it is written — a device may only act for a customer on its own rep's route, checked server-side rather than trusted from a payload that already carries a cached (and, per §4, occasionally stale) copy of the rep's book.

### 8. Auth and transport

Checked empirically before deciding: this install has no `routes/api.php`, no API middleware group wired in `bootstrap/app.php`, and Sanctum is not installed. Adding it for this alone would be a new package for something the stack already has an answer to (source of truth §8 requires a written reason for any addition beyond §3, and "the session guard already does this" isn't one).

Sync endpoints are plain `web`-guarded routes, authenticated the same way the back office already is, with Laravel's standard CSRF protection applying to them exactly as it does to every other web route. The PWA is same-origin, so a small inline script places the CSRF token where the service worker's upload queue can read it before each request, the same way any Blade page hands a token to `fetch()`.

The one real gap is session lifetime: a rep offline all day can outlive the default 120-minute session. Login for reps sets Laravel's standard "remember me" cookie, which is already wired into `Authenticatable` and needs no new code — it outlives the session and re-establishes it silently on the next request. That, plus the manual sync button as a fallback, is enough; nothing here needs a token scheme.

### 9. Device registry

Minimal, because nothing in this phase needs more than: a device is a ULID the PWA generates once and keeps in `localStorage`, tied to a `sales_rep_id`, with a `last_seen_at` it updates on every successful exchange and a free-text label (`navigator.userAgent`, truncated) so a manager looking at the sync status screen can tell one rep's phone from another. It exists to give the audit log a `device_id` to key on and the status UI something to show, not to manage fleets of hardware.

### 10. Explicitly out of scope for v1

Named here so a gap found later reads as a decision, not an oversight:

- Line-level or partial-order sync — §7's `OrderIntake` takes a whole order.
- Any merge of a genuinely two-sided edit — ruled out entirely by §3.
- The Background Sync API as the *only* trigger — it's Chromium-only. The queue flushes on app open, on the browser's `online` event, on a periodic timer while the app is open, and on the manual button the phase's own acceptance criterion already requires. Background Sync, where present, is a bonus on top of those, not the mechanism itself.
- Any packaged sync/offline library (Workbox, vite-plugin-pwa, Sanctum). The service worker, the manifest and the IndexedDB layer are hand-written, per source of truth §8.

## Consequences

The push endpoint's correctness rests on one unique constraint (`client_id`) doing the hard work, with the audit-log-as-idempotency-store pattern built on top of it rather than beside it — less machinery than a separate idempotency table, and it can't drift out of sync with the log because it *is* the log.

Ruling out two-sided edits (§3) is the single decision that keeps this phase buildable in its budget. It is a real limitation — a manager cannot yet edit an order the moment it lands from a device without that edit being purely a back-office operation from then on — but it is the honest v1 boundary the phase's own contingency clause anticipates, not a corner cut silently.

The customer hard-delete gap in §4 is a known, narrow limitation: a device that misses a customer's deletion keeps showing it, inactive-looking data aside, until it's told otherwise by some other means. Acceptable at this scale; worth a look if multi-device fleets grow past the size where "tell the rep to reinstall" is a reasonable answer.

Pre-resolving prices (§5) means the pull endpoint does real work per rep rather than serving static rows, which is more server-side computation than a naive "ship the tables" design — but it is the only version of this that doesn't risk a pricing bug shipping twice, once in PHP and once in JavaScript, and a route's worth of resolved prices is not an expensive query to run.
