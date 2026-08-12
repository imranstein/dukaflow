# Active context

What's in flight right now. Read first when picking the project back up; update before putting it down.

- **Current phase**: Phases 0 through 3 are done. Next up is Phase 4, polish and launch readiness.
- **Next action**: run the strict read-only adversarial review against the Phase 3 diff, fix what's real, tag `v0.4.0-beta`. Then start Phase 4 with a short plan checked against the source of truth, same as every phase before it.
- **Done in Phase 3**: ADR-002 (sync strategy) and ADR-003 (id strategy) written before any sync code, per the project's own working convention. A `Sync` module: idempotent push (orders, visit outcomes), cursor-based delta pull, a per-rep pre-resolved pricebook, price-variance flagging, conflict records instead of silent overwrites. A hand-written PWA at `/rep`: service worker, IndexedDB, an Alpine capture flow that makes zero server round-trips per interaction, an upload queue with retry and backoff. CI now builds and checks the frontend on every push.
- **State**: 357 Pest tests green on SQLite and MySQL, Larastan level 6 clean, Pint clean, CI green (including the new frontend build step). Verified end to end in the browser: log in as a rep, see today's round pulled from the server, capture an order and a no-sale outcome, watch them sync, confirm nothing duplicates on a repeat sync.
- **Blockers**: none.

## Worth knowing before you touch anything

- The stack is newer than most tutorials: **Laravel 13, Filament 5, Livewire 4, Pest 4**. Filament 5 moved forms to `Filament\Schemas\Schema` and actions to `Filament\Actions\*`, and tables use `recordActions()` / `toolbarActions()`. Generate Filament classes with artisan rather than writing them from memory.
- `make:filament-resource` infers the target directory from existing resources, not from the model namespace. It put the Distribution resources under Catalog; the arch test caught it. Check where generated files land.
- Pest closures break Larastan: `$this->foo` inside `it()` resolves to `TestCall`. Use the `Pest\Laravel\*` functions and local variables instead of `beforeEach` properties.
- **`client_id` lives on the row, not only in the audit log.** `OrderWriter::startDraft()` takes an optional `clientId` that has to be threaded all the way from the HTTP payload through `OrderIntake` — it's easy to wire the idempotency check correctly (via `SyncAuditLog`) and still leave the order's own `client_id` column null, because idempotency working correctly doesn't prove the column got set. Caught by hand in the browser, not by a test, on the first live run.
- **`crypto.randomUUID()` is not a ULID.** `client_id`/`device_id` are `char(26)` ULID columns; a 36-character UUID either truncates or 422s against `PullSyncRequest`'s `max:26`. There's a hand-rolled Crockford-base32 `ulid()` in `resources/js/rep/ulid.js` for exactly this — use it, not the browser's built-in UUID generator, anywhere a client_id or device_id is generated.
- **"Visited today" cannot be derived from the sync queue.** A completed visit's whole purpose is to leave the queue once it syncs, so a queue-only check makes the checkmark vanish the moment sync succeeds. It has to be its own persisted, date-scoped record (`visited:YYYY-MM-DD` in IndexedDB's `meta` store), written at capture time and never cleared by a successful push.
- Laravel's default guest-redirect middleware calls `route('login')` unconditionally; this app has never had that route (Filament owns its own auth flow, the rep interface has `rep.login`). `bootstrap/app.php` overrides `redirectGuestsTo()` to point at `rep.login` — the only generic guest-auth surface this app has outside Filament.
- The app's `shouldRenderJsonWhen()` only renders JSON under `/api/*`; the sync endpoints live at `/api/sync/*` for exactly that reason, on the plain `web` middleware group (session + CSRF), not a separate API guard — see ADR-002 §8.
