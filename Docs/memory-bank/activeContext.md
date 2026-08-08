# Active context

What's in flight right now. Read first when picking the project back up; update before putting it down.

- **Current phase**: Phases 0 and 1 are done. Next up is Phase 2, orders and inventory.
- **Next action**: sketch the Phase 2 plan against the source of truth. Start with an ADR on the order state machine, then the append-only stock ledger — the stock invariant (never negative without an explicit adjustment) is the thing to get right first.
- **Do this before the stock ledger**: add MySQL to the CI matrix. The whole suite runs on SQLite while the source of truth names MySQL the primary target, so the primary target is never tested. SQLite enforces neither `VARCHAR` length nor `DECIMAL` scale, which already hid a four-character value sitting happily in a `string(3)` currency column. Ledger invariants are transaction and constraint behaviour, which is exactly where the two databases diverge most.
- **State**: 100 Pest tests green, Larastan level 6 clean, Pint clean, CI green. Sail verified on PHP 8.3 with MySQL 8.4 alongside.
- **Blockers**: none.

## Worth knowing before you touch anything

- The stack is newer than most tutorials: **Laravel 13, Filament 5, Livewire 4, Pest 4**. Filament 5 moved forms to `Filament\Schemas\Schema` and actions to `Filament\Actions\*`, and tables use `recordActions()` / `toolbarActions()`. Generate Filament classes with artisan rather than writing them from memory.
- `make:filament-resource` infers the target directory from existing resources, not from the model namespace. It put the Distribution resources under Catalog; the arch test caught it. Check where generated files land.
- Pest closures break Larastan: `$this->foo` inside `it()` resolves to `TestCall`. Use the `Pest\Laravel\*` functions and local variables instead of `beforeEach` properties.
