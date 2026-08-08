# ADR-005: Order lifecycle and what an order remembers

- Status: accepted
- Date: 2026-08-08

## Context

An order moves through a handful of states, and the transitions are not free-for-all: approving an order nobody submitted is meaningless, and fulfilling one that was cancelled is worse than meaningless because stock moves. Phase 3 adds a second way for orders to arrive — captured on a handset, hours old, possibly out of order — so the rules have to be somewhere a sync endpoint can apply them, not spread across form handlers.

There is a second question that looks like a detail and is not. An order line records a product and a price. Both of those live in Catalog and both change: products get renamed, discontinued and re-packed; price lists are superseded. What should an order printed six months later say?

## Decision

### The states, and who may move between them

```
draft ──> submitted ──> approved ──> fulfilled
  │           │             │
  └───────────┴─────────────┴────────> cancelled
```

- **draft** — being built. The only state in which lines may be added, changed or removed.
- **submitted** — the rep has sent it in. Lines are frozen.
- **approved** — the office has accepted it. Stock is committed against it.
- **fulfilled** — the goods have gone. Terminal.
- **cancelled** — abandoned, from any state before fulfilled. Terminal.

Guards, all enforced in the model and all tested directly:

- An order with no lines cannot be submitted. An empty order is a mistake, not a state.
- Lines can only change while the order is a draft.
- A fulfilled order cannot be cancelled. The goods are gone; reversing that is a return, which is a different transaction and not in this phase.
- Every transition stamps its own timestamp, so the history is on the row rather than inferred from an audit table.

Illegal transitions throw rather than returning false. A caller that asks for something impossible has a bug, and swallowing it produces an order sitting in a state nobody chose.

### An order line is a snapshot, not a pointer

Each line stores `product_id` **and** the product's SKU, name and unit code as they were when the line was written, alongside the unit price and the price list the price came from.

This is deliberate duplication, which normally deserves suspicion. The justification: the line is a record of an agreement, not a view of the current catalogue. If a product is renamed from "Ambo Mineral Water 1L" to "Ambo Sparkling 1L", last month's orders must still say what was actually agreed. Joining to the live product would silently rewrite history, and the distributor would only discover it during a dispute.

Keeping `product_id` as well means reporting can still group by product; the snapshot is for display and for the record, not a replacement for the relationship.

Recording `price_list_id` on the order is what makes Phase 3's price-integrity rule possible: an order captured offline names the list it was priced under, and the server can then tell whether that list still says what the handset thought it said.

### Totals are stored, and checked

`total_minor` is written on the order and recalculated whenever lines change. Deriving it on read would be the purer choice, but every order list in the application would then carry a subquery. The risk of a stored total is that it drifts from its lines, so a test asserts they agree after every operation that touches either.

### Payments are ledger entries

A payment is a row saying an amount arrived, by cash or on credit, on a date. There is no gateway, no capture, no reconciliation against a processor, and there will not be — the brief makes that a hard boundary. An order can be partly paid; the balance is the total minus the sum of its payments.

## Consequences

The snapshot columns mean an order line is wider than it looks and cannot be corrected by fixing the product. That is the intent, but it will surprise someone: changing a product's name does not change past orders, and it should not.

Guarded transitions that throw mean the UI has to ask before it acts rather than offering every button and handling failure. Filament resources are built accordingly, showing only the actions the current state allows.

Storing the total buys fast lists at the cost of an invariant to maintain. The invariant is cheap to test and expensive to lose.
