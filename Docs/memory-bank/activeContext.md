# Active context

What's in flight right now. Read first when picking the project back up; update before putting it down.

- **Current phase**: Phases 0, 1 and 2 are done. Next up is Phase 3, the rep PWA and offline sync — the centrepiece.
- **Next action**: write ADR-002 on the sync strategy **before any sync code**. The source of truth section 5 is the contract it has to detail. Orders already record the price list they were priced under, which is what the price-integrity rule needs.
- **Done in Phase 2**: MySQL is in the CI matrix now, so the suite runs on both databases.
- **State**: 283 Pest tests green on SQLite and MySQL, Larastan level 6 clean, Pint clean, CI green. Sail verified on PHP 8.3.
- **Blockers**: none.

## Worth knowing before you touch anything

- The stack is newer than most tutorials: **Laravel 13, Filament 5, Livewire 4, Pest 4**. Filament 5 moved forms to `Filament\Schemas\Schema` and actions to `Filament\Actions\*`, and tables use `recordActions()` / `toolbarActions()`. Generate Filament classes with artisan rather than writing them from memory.
- `make:filament-resource` infers the target directory from existing resources, not from the model namespace. It put the Distribution resources under Catalog; the arch test caught it. Check where generated files land.
- Pest closures break Larastan: `$this->foo` inside `it()` resolves to `TestCall`. Use the `Pest\Laravel\*` functions and local variables instead of `beforeEach` properties.
