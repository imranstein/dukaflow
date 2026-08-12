# Domain glossary

The trade has its own words, and the codebase uses them rather than generic ones — a `Route` is a beat, not an HTTP path; an `Outlet` is a shop, not a power socket. This is the reference for both.

## Distribution

- **Outlet / customer** — a shop a rep sells to. Modelled as `Customer` for historical reasons (it was the first name used), but every doc and every screen calls it an outlet. Has a type (kiosk, wholesaler, supermarket, ...), a location, and sits on a route.
- **Route / beat** — the run of outlets one rep works through, in a fixed order. "Beat" is the word used on the ground; "route" is the word in the schema.
- **Visit schedule** — which day of the week an outlet is due a call, and where it falls in that day's sequence. One outlet can have more than one visit day.
- **Visit outcome** — what actually happened on a call, as opposed to what the schedule expected: an order, or a no-sale with a reason. Only no-sales get their own record — a placed order is its own evidence that the visit happened.
- **Sales rep** — field staff. Works the PWA; the back office is read-only to them.

## Catalog

- **Price list** — a named set of prices, with an effective date range and a currency. A product can be priced differently on different lists.
- **Price list assignment** — attaches a price list to a specific customer or a specific route. The narrowest assignment wins: customer beats route beats the house default.
- **Unit of measure** — how a product is sold (carton, sack, piece). Carried alongside the product's pack size.

## Orders

- **Order lifecycle** — draft → submitted → approved → fulfilled, or cancelled from any point before fulfilled. Each transition is guarded; an illegal one throws rather than silently doing nothing.
- **Order line** — one product on an order, with its quantity and price *copied* from the catalogue at the moment it was added. An order is a record of an agreement, not a live view — renaming a product later doesn't rewrite last month's paperwork.
- **Reference** — the human-facing order number, `SO-2026-00001` style. Sequential per year. Not the same thing as the database id, and not the same thing as `client_id` below.
- **Fulfilled** — the point at which goods actually leave and stock moves. Approving an order does not move stock; fulfilling it does.
- **Price variance** — set on an order when a price captured offline disagreed with what the pricebook says now, by the time it synced. The order keeps the rep's original price; the flag is only for a manager's attention.

## Inventory

- **Stock movement** — one append-only row: what moved, how much, where, when, why. A balance is the sum of every movement for a product at a location, never a column that gets edited.
- **Adjustment** — the one kind of movement that can take a balance below zero, and the only kind a person writes directly (everything else is written by the system in response to something happening — a receipt, a sale, a reconciliation).
- **Reconciliation** — counting a rep's van at the end of the day. Closing one compares the count against the live ledger and writes an adjustment for whatever doesn't match.
- **Location kind** — stock sits at a warehouse or on a rep's van. A van isn't a row in a table anyone owns; it's just "wherever this rep's stock currently is."

## Sync

- **Client ID** — a ULID a device generates for itself, for a record it creates offline (an order, a visit outcome). This is the identity the whole idempotency scheme hangs off; the database's own auto-increment id is an internal detail the device never sees.
- **Idempotent submission** — resubmitting the same client ID with the same content is a no-op that returns the original result. No duplicate orders, ever, even if the network drops a response after the write already succeeded.
- **Conflict** — a client ID reused for *different* content. Flagged for a human to look at; never merged, never silently overwritten.
- **Cursor** — a device's bookmark for a delta pull: everything changed since this point, in this exact order. Built from a timestamp and an id together, because a timestamp alone can drop a row two records updated in the same second.
- **Pricebook (per rep)** — the flat, pre-resolved `(customer, product) → price` table a device pulls, rather than the raw price lists and the logic to resolve them. The resolution runs once, server-side; the device just looks a number up.
- **Device** — a ULID the PWA generates once and keeps locally. Exists so the audit log and the sync status screen have something to key on, not to manage a fleet of hardware.

## Everywhere

- **Money** — never a float. Every amount is a whole number of minor units (santim, cents) inside a `Money` value object; a currency code travels with it everywhere.
- **Bare id** — an integer stored with no foreign key, because the table it points to belongs to another module. The pattern that keeps modules from needing to know about each other's schemas.
