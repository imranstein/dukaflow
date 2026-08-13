# ADR-008: line-level order sync — considered, not built

- Status: rejected (revisit if the trigger in Consequences shows up)
- Date: 2026-08-13

## Context

ADR-002 §10 names "line-level or partial-order sync" as explicitly out of scope for v1 — `OrderIntake` takes a whole order, built and submitted in one call. It's named there so a gap found later reads as a decision, not an oversight, and it's one of three candidates this ADR-002 section pointed at for v1.1. Naming it a candidate was never a commitment to build it; §10's own job is to record what wasn't decided yet, not what was.

So the question isn't "how would line-level sync work" — it's "does the problem it would solve actually exist, and is it worth what building it costs." Skipping straight to a design would answer the wrong question first.

**What line-level sync would mean:** a rep captures an order, it syncs, and later — same visit or later that day — the rep adds another line to that *same* order from the device, rather than creating a new one.

**What already happens today, for free:** the rep captures a second order for the same customer. Nothing stops it — `orders` has no per-customer-per-day uniqueness constraint, only `reference` (auto-generated) and `client_id` (the idempotency key) are unique. Two orders, two references, both flow through the same push/pull/pricing/variance machinery already built.

## Decision

**Not built.** Two of this design's load-bearing rules would have to bend for it, and the thing it would buy over "capture a second order" doesn't clear that bar.

### What it collides with

**ADR-002 §3's read-only-once-synced rule**, which that section itself calls the decision that keeps the whole conflict surface small: "once an order has synced, it's read-only from the device's side... nothing on the device ever re-submits a change to an order that already exists server-side." Line-level sync is exactly that re-submission, just scoped to appending rather than editing.

**`OrderStatus::allowsEditingLines()`**, which is `Draft`-only by ADR-005's own state machine — and `OrdersIntake::submit()` (the code that actually runs when a synced order lands) calls `$order->submit()` in the same transaction that creates it. A synced order is never left in Draft; it goes straight to `Submitted`, into the same approval queue a manager keying one in by hand would land in. There is no window where the device's own order is still editable server-side by the time it's finished syncing — "append a line" would mean reopening a `Submitted` order back toward `Draft`, which ADR-005's guarded transitions don't offer and shouldn't: a manager could already be reviewing it.

Neither collision is a small workaround. Reopening the state machine for this one case reintroduces exactly the two-sided-edit risk ADR-002 §3 exists to rule out — a device silently appending to an order a manager is mid-approval-on is a genuine conflict, not a sync detail.

### What it would have to solve to be worth it, and doesn't

An appended line needs its own price resolution (a price list in force at the *original* `placed_at`, or a new timestamp for just that line?), its own variance flag independent of the lines already on the order, and a decision about whether appending re-opens the order for a manager who already approved it. All of that is new surface area, not a small addition to `OrderIntake`.

Against that: a second order costs nothing new and already carries pricing, variance flagging, and the approval workflow correctly. The one real difference a manager or a delivery run would see is two order references instead of one for the same visit — and nothing in `Docs/SOURCE_OF_TRUTH.md` or the schema treats "one order per visit" as a requirement. It was never asked for; it would be solving a problem this application doesn't have evidence of yet.

## Consequences

Nothing changes in the codebase. `OrderIntake::submit()` keeps taking a whole order; ADR-002 §10 keeps naming this as deliberately unbuilt rather than this ADR silently closing the paragraph.

**Revisit if:** an actual operational need for single-order-per-visit shows up — delivery consolidation that can't handle two references for one drop, or reps reporting real friction from re-keying a whole new order for one extra case. Until then, "capture a second order" is the answer, and it costs nothing to keep being the answer.
