# Contributing

Thanks for taking a look. This started as a solo project, but the conventions below are the ones any change — mine or yours — is expected to follow.

## Before you write code

Read [Docs/SOURCE_OF_TRUTH.md](Docs/SOURCE_OF_TRUTH.md) first. It's short, it wins every disagreement with any other doc, and it explains the non-goals — things deliberately not being built (payment gateways, native apps, multi-tenancy, and a few others) that a well-meaning PR sometimes tries to add anyway.

If you're proposing something bigger than a bug fix, open an issue first. It's a much shorter conversation before the code exists than after.

## The rules that actually get enforced

CI blocks a merge if any of these fail, so there's no ambiguity about whether they're optional:

- **Formatting** — `./vendor/bin/pint`. Run it before you commit; don't hand-format PHP.
- **Static analysis** — `./vendor/bin/phpstan analyse`, at level 6. No baseline entries or `@phpstan-ignore` comments to silence a real finding — fix the type, don't hide it.
- **Tests** — `./vendor/bin/pest`, on both SQLite and MySQL. A change to domain logic (pricing, order transitions, the stock ledger, sync idempotency, conflict rules) needs a direct test of that logic, not just an HTTP smoke test that happens to exercise it.
- **Module boundaries** — `tests/Arch/ArchTest.php` and `tests/Arch/ModuleBoundaryTest.php`. A module in `app/Modules/` may not import another module's models, query its tables, or reference its classes by name — not even from a migration or a factory. Cross-module needs go through an interface in `app/Support/`, the shared kernel. If you're not sure how, `ScopeDirectory` and `Pricebook` are the two smallest worked examples.

Run all three locally before opening a PR:

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

## Style

Boring, idiomatic Laravel over clever abstractions. Readability outranks brevity — part of the point of this project is that the code should be worth reading. No new Composer or npm package without a real reason, and specifically: no sync, offline, or storage package of any kind. That layer is hand-built on purpose; see [ADR-002](Docs/adr/0002-offline-sync-strategy.md) for why.

Every decision that took real thought gets an [ADR](Docs/adr/) — a short doc with the context, the decision, and the consequences, following the numbered files already there. If your change makes an existing one stale, update it in the same PR rather than leaving it to drift.

## Commits and PRs

Conventional commits (`feat:`, `fix:`, `docs:`, and so on), small and focused — the history is meant to be readable, not squashed into one giant diff per feature. A PR that does two unrelated things is two PRs.

Describe what changed and why, not just what. If the change touches a module boundary, a migration, or anything sync-related, say so explicitly — those get a closer look.

## Filament specifics

This project targets Filament 5 and Livewire 4, both newer than a lot of what's indexed for AI assistance and older tutorials. If you're generating a resource, use `artisan make:filament-resource` rather than writing the schema from memory — the API moved (`Filament\Schemas\Schema` for forms, `Filament\Actions\*`, `recordActions()` / `toolbarActions()` on tables) and a plausible-looking older API will compile-fail or silently do nothing.

## Questions

Open an issue. There's no separate chat or mailing list for this project yet.
