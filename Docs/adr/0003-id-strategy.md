# ADR-003: client-generated ids for records born offline

- Status: accepted
- Date: 2026-08-12

## Context

Every record built so far gets its identity from the database: an auto-increment id, handed out on insert, unique because only one writer — the server — ever hands one out. Phase 3 breaks that. A rep's phone creates an order with the network off, with no server anywhere nearby to assign it a number, and it may create several before it ever reconnects. Two questions follow: what identifies that order before the server has seen it, and how does the server, on first seeing it, tell a genuinely new order from a resubmission of one it already has.

The source of truth is explicit that the client generates the id and the server never renumbers. The open question this ADR settles is narrower: does that client-generated id *replace* the auto-increment primary key, or sit alongside it.

## Decision

It sits alongside it. The primary key stays an auto-increment `bigint`, exactly as every table in this codebase already has it. A new nullable `client_id` column, a ULID, carries the identity the sync protocol actually cares about.

Two things point the same way here. First, cost: Orders already has two phases of working, reviewed, tested code built on integer ids — `order_lines.order_id`, `order_payments.order_id`, `stock_movements.reference_id`, every factory, every Larastan `@property int $id`. Rekeying all of it to make the primary key itself a ULID is a wide rewrite for a rule that doesn't actually require it. Second, and more simply: "the server never renumbers" is a promise about identity, not about storage. A ULID column that never changes once written keeps that promise exactly as well as a ULID primary key would, and it does it without disturbing every foreign key in Phase 2.

**Only records a client can create get one.** That's orders and visit outcomes. Catalog, distribution and pricing data are written in the back office and only ever flow down to a device, never up, so nothing about them needs a client-assigned identity — they keep the plain integer id they already have. `client_id` is nullable for exactly this reason: a back-office order (a manager keying in a phone order, say) has no device and no client id, and that's a normal row, not an incomplete one.

Order lines and payments are not independently addressed. An offline order arrives as one document — the order and all its lines together — so the lines ride on their order's identity. There is no scenario in v1 where a single line syncs on its own, so a line does not need its own `client_id`.

**ULID, not UUIDv4.** A ULID is lexicographically sortable and carries a millisecond timestamp in its first 48 bits. A phone generating several of these offline through the day produces ids that already sort in creation order, which is a small but free property to have when an audit log later needs to show "what did this device create, in what order" without leaning on a `created_at` that the phone's own clock supplied. Laravel's `Str::ulid()` is stdlib to this codebase already; nothing new to install.

**Uniqueness.** `client_id` is unique where not null. That constraint is the actual mechanism idempotency leans on later in ADR-002 — a second push carrying the same `client_id` cannot become a second row, full stop, independent of anything the sync layer does or forgets to do in application code.

## Consequences

One migration per client-facing table (`orders`, and the new `visit_outcomes`) rather than a rewrite of Phase 2. Every join, factory and type annotation already written keeps working unchanged.

The order's human-facing identity was already the `SO-2026-NNNNN` reference, not the integer id, so nothing about this decision changes what a person looking at the back office sees. `client_id` is a sync-protocol detail, invisible outside it.

The gap this leaves: if v1.1 ever needs to sync something more granular than "one whole order," Orders needs a second look then. Not needed for the entities this phase actually has to move, so not built now.
