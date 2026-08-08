# ADR-006: Stock as an append-only ledger

- Status: accepted
- Date: 2026-08-08

## Context

A distributor's stock sits in two kinds of place: a warehouse, and the back of a van. It moves between them all day — loaded onto a rep in the morning, sold down through the round, whatever is left brought back and counted at night. The count at the end almost never matches the arithmetic, and the whole job of end-of-day reconciliation is to find out why.

The obvious model is a `quantity` column you add to and subtract from. It is also the model that makes "why is this number wrong?" unanswerable, because the evidence was overwritten each time.

## Decision

Stock is not a number. It is the sum of every movement ever recorded.

`stock_movements` is append-only: rows are inserted, never updated and never deleted. Each row says what moved, how much, from or to where, when, why, and what caused it. A balance is `SUM(quantity)` for a product at a location. Nothing anywhere stores a current quantity.

- **Signed quantities.** Positive is in, negative is out. One column, no direction flag to get backwards.
- **Location is a kind and an id** — `warehouse` or `van` — rather than two nullable foreign keys. A van is not a row in a table anyone owns; it is a rep, and reps live in Distribution, which Inventory does not depend on.
- **Every movement has a type**: receipt, van load, van return, sale, adjustment. The type is why it moved, and it is what makes a ledger readable months later.
- **Movements caused by something else record it** — a sale names the order it came from — so a balance can always be traced back to the documents that produced it.

### The invariant

**A movement may not take a balance below zero, unless it is an adjustment.**

This is the rule the whole module exists to protect. You cannot sell stock a rep does not have, and you cannot load out of an empty warehouse. But you must be able to record that reality disagrees with the ledger, because sometimes it does — breakage, theft, a miscount that morning. That is what an adjustment is: an explicit, attributable statement that the books were wrong. It is the only way a balance goes negative, and because it has its own type it is trivially auditable.

The check runs inside a transaction that locks the product's existing movements at that location. This is a coarse lock — it serialises concurrent writes for the same product and location, which is the correct trade for correctness here, since the alternative is two reps selling the same last case. If throughput ever matters, the upgrade is a per-location balance row to lock instead of the movement range; it is not needed at the volumes this application is for.

SQLite does not enforce this the way MySQL does, which is why the test suite runs against both.

### Reconciliation

At the end of a day a rep's van is counted. A reconciliation holds one line per product with the expected quantity (from the ledger) and the counted quantity (from the rep). The variance is the difference, and it is *reported*, not silently applied.

Closing a reconciliation is what writes the adjustments — one movement per non-zero variance, typed as an adjustment, referencing the reconciliation. That is the only place in the application that creates adjustments automatically, and it leaves a complete trail from "the ledger said 12, we counted 11" to the row that made the books agree.

## Consequences

Reading a balance costs an aggregate over the movements rather than a column read. At the scale of one distributor that is nothing; at a much larger scale it becomes a periodic snapshot row that later movements sum on top of. The shape of the fix is well known, and nothing about this design blocks it.

Nothing can be quietly fixed. Correcting a mistake means recording another movement, which is the point — the ledger is evidence, and evidence you can edit is not evidence. It does mean the table only ever grows.

The append-only rule is enforced in the model, not just documented: the model refuses to be updated or deleted.
