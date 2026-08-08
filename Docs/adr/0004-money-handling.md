# ADR-004: Money handling

- Status: accepted
- Date: 2026-08-08

## Context

Prices arrive in Phase 1 with price lists and stay for the rest of the project: order lines, payment records, reconciliation variances. Getting the representation wrong early is expensive to undo, because every one of those tables would need migrating.

The failure mode is well known. `0.1 + 0.2` is `0.30000000000000004` in IEEE 754, and a `decimal` column read into a PHP float inherits the same problem. A distributor reconciling a day of van sales against a cash count notices a one-santim drift, and there is no good answer for where it came from.

There is a second, quieter problem: an amount without a currency is not an amount. Passing a bare `1250` around means every function that touches it has to already know what currency it is, and nothing stops two currencies being added together.

## Decision

Money is an immutable value object, `App\Support\Money`, holding a whole number of minor units and an ISO 4217 currency code. It lives in the shared kernel because every module needs it and none of them should own it.

- **Storage**: integer columns of minor units (santim for Birr), plus a currency column where the currency can vary. Never `float`, never `double`. `decimal` would be accurate in the database but is handed to PHP as a string or float, so the discipline has to live in the application anyway.
- **Construction**: `Money::ofMinor(1250)` or `Money::fromDecimal('12.50')`. The decimal constructor takes a string, not a float — a float argument has already lost precision before the method runs.
- **Arithmetic**: `plus`, `minus` and `multipliedBy` return new instances. Combining two different currencies throws rather than silently coercing.
- **Multiplication takes an integer.** Quantities in this domain are whole pieces, cases and crates. Restricting the factor to `int` means multiplication is exact and there is no rounding policy to argue about. When Phase 2 introduces percentage discounts, that will need an explicit rounding mode, and it gets its own decision then rather than being smuggled in now.

Two decimal places are assumed for every currency. Birr, shilling, naira, dollar and euro all have two. A currency with a different subunit — the dinar has three, the yen has none — would turn `SUBUNIT_DIGITS` into a per-currency lookup. That is a real limitation, written down here rather than discovered later.

## Consequences

Reading a price out of the database means constructing a `Money` from the integer column, and Filament fields have to convert on the way in and out. That is more ceremony than binding a form straight to a decimal column.

What it buys: arithmetic that is exact by construction, a type that cannot be confused with a quantity or an ID, and currency mismatches that fail loudly at the point of the mistake instead of producing a plausible wrong number three screens later.
