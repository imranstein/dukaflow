# ADR-007: reconciling stale device caches

- Status: accepted
- Date: 2026-08-13

## Context

ADR-002 §4 already names a gap and accepts it for v1: "a route reassigned away from a rep simply stops appearing in that rep's future pulls... a device that already cached it locally keeps a stale copy until told otherwise by some other means." `DistributionSyncFeed`'s own docblock repeats it for the same reason. Named deliberately, per §10, so it reads as a decision rather than an oversight — and ADR-002 §10 names it as one of v1.1's candidates.

The pull is a delta query: "rows changed since this cursor." That can only ever return rows that still match the query. It has no way to say "row 41 used to match and no longer does" — whether because the row was deleted, or because a scoped row (a route, in particular) was re-pointed at a different rep and so no longer matches *this* rep's `WHERE sales_rep_id = ?`. From the device's side these look identical: a row it cached is no longer valid, and nothing tells it so.

Checked while writing this ADR, not assumed: `CatalogSyncFeed`'s docblock claims "a product never actually disappears from the feed," reasoning that deactivation is the only deletion a device sees (§4). That is true for deactivation, but `ProductsTable` and `EditProduct` both offer a real `DeleteAction`, and `Product` has no `SoftDeletes` — a product can be hard-deleted today, and the claim overstates what the feed actually guarantees. This ADR's scope grew by one entity type because of that: the gap is not Distribution-specific, it is a property of "any row a scoped or filterable query can stop returning," and product qualifies.

## Decision

### 1. Options considered

**Tombstones via `ScopeRecordDeleted`.** This codebase already has a mechanism for "a module needs to know when another module's record it references by bare id has disappeared" — `ScopeRecordDeleted`, fired from `Customer::booted()` and `Route::booted()` on `static::deleted()`, already consumed by Catalog's `PurgeAssignmentsForDeletedScope` to clean up price list assignments. Reusing it for Sync would mean: a new listener recording removals into a table, and the pull including removals since the cursor — "removals are just another delta feed," consistent with how everything else here already syncs.

It only covers half the problem. `ScopeRecordDeleted` fires on delete; a route reassignment is an *update* (`sales_rep_id` changing), which fires nothing today. Covering both would mean partially reusing an existing event and inventing a new one for the other case — two different signal sources for what is, from a device's point of view, the exact same symptom. And both still depend on a model event firing: ADR-006 already documents, and deliberately leaves unfixed, that this codebase's model-event guards do not survive a bulk update through the query builder. Nothing does that today for `Route` or `Customer`, but that was also true of the stock ledger's guard until someone wrote the test that says so out loud.

**Full id-set reconciliation.** The server hands back, alongside the delta rows, the complete set of ids currently valid for that entity type and scope. The device prunes anything it has cached that is not in that set. This does not depend on capturing an event at the moment something changed — it is correct by construction, because it never asks "what happened," only "what's true right now." A route reassignment and a hard delete produce the identical symptom (the id is no longer in the set) and are handled by the identical code path, which is the more direct match for a problem that is itself indifferent to *why* a row left scope.

**Decision: full id-set reconciliation.** It unifies both cases under one mechanism instead of two, needs no new event and no removals table with its own retention question, and cannot be silently defeated by a future write path that does not happen to go through a model's `save()`/`delete()`.

### 2. The mechanism

`SyncFeed` gains a third method alongside `entityTypes()` and `pull()`:

```php
/**
 * Every id currently valid for this entity type and scope — the
 * authoritative set a device reconciles its cache against. Not a delta;
 * recomputed from the same query pull() itself filters by, so a
 * reassigned or hard-deleted row simply stops appearing, the same way it
 * already stops appearing from pull().
 *
 * @return list<int>
 */
public function idsInScope(string $entityType, ?int $salesRepId): array;
```

`CompositeSyncFeed` delegates it the same way it delegates `pull()`. Each feed implements it directly off the same filtered query `pull()` already builds — for `DistributionSyncFeed`, `Customer::query()->whereIn('route_id', $routeIds)->pluck('id')` and so on; for `CatalogSyncFeed`, `Product::query()->pluck('id')` (unscoped, matching `pull()`'s own lack of rep scoping there).

`SyncPullController` calls it once per entity-type pull, but only when the page just returned is the last one (`has_more === false`) — reconciling against a snapshot mid-pagination would prune ids the device hasn't even finished catching up on yet, and would mean computing it on every page of what might be several hundred rows for no reason. The response gains one field on that final page:

```json
{
  "entity_type": "route",
  "rows": [],
  "next_cursor": "...",
  "has_more": false,
  "valid_ids": [12, 14, 19]
}
```

Client-side, `db.js`'s `putCatalogRows(entityType, rows)` gets a counterpart — `pruneCatalogRows(entityType, validIds)` — called when `valid_ids` is present in a response: delete every row in the `catalog` store with that `entity_type` whose id is not in the set. One IndexedDB range delete, not a per-row diff.

### 3. Scope: which entity types

`customer`, `route`, `visit_schedule` (the three ADR-002 §4 already named), and `product` (found above). `visit_schedule` needs it for a second reason beyond the direct one: a hard-deleted customer cascades to delete its visit schedules (`visit_schedules.customer_id` is `cascadeOnDelete()`), so a schedule can drop out of scope without its own row ever having been touched.

Orders and visit outcomes are excluded — deliberately, not by omission. They flow up, never down (ADR-002 §1), and become read-only from the device's side the moment they sync (§3). There is no cached copy on the device to reconcile against; the device's own record of "I already submitted this" is exactly right for as long as it exists, per §2.

### 4. What this still doesn't solve

A device only reconciles on its next full pull of that entity type — the same cadence every other change already reaches it on, no faster and no slower. A rep who stays offline through a reassignment keeps working against the stale copy until they next sync, which is the correct trade for this application: nothing here promises real-time propagation, and §6 already accepts a device's view of the world lagging the server's by however long it's been offline.

It also does not, and should not, tell the device *why* a row disappeared. Reassigned to someone else, hard-deleted, deactivated-then-later-hard-deleted — the device does not need to distinguish any of these to do the one thing it needs to do, which is stop showing a rep a customer or route that is no longer theirs.

## Consequences

Payload cost is bounded by the same scale ADR-002 §5 already accepted paying for the pricebook: a rep's route is a few hundred ids at most, sent once per full pull of an entity type, not per page.

`CatalogSyncFeed`'s docblock ("a product never actually disappears from the feed") and `DistributionSyncFeed`'s docblock (the "known gap, accepted for v1" paragraph) both get corrected in the change that implements this — the gap they describe is what this ADR closes, and leaving them saying otherwise would be exactly the kind of doc drift the last review found and fixed elsewhere.

Testing follows the shape `SyncFeedTest.php` already established for scope isolation: reassign a route away from a rep (or hard-delete a customer), pull as that rep, assert the device's `valid_ids` no longer contains it — not just that a fresh pull doesn't re-offer it, which was already true before this ADR and proves nothing about the stale copy.
