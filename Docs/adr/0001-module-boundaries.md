# ADR-001: Module boundaries

- Status: accepted
- Date: 2026-08-08

## Context

DukaFlow is a modular monolith. The domain splits cleanly into five areas — Catalog, Distribution, Orders, Inventory, Sync — and the project brief requires each to own its migrations, models, admin resources, policies, and tests. Two questions had to be settled before any code was written: where the modules live on disk, and what "a module owns its own things" means concretely.

## Decision

Modules live in `app/Modules/<Module>/`, namespaced `App\Modules\<Module>`.

`src/Modules/` was the alternative. It reads slightly better as "this is not stock Laravel", but it needs a new PSR-4 autoload root in `composer.json` and a `dump-autoload` before anything runs. `app/Modules/` is covered by the default `App\` → `app/` mapping that every Laravel project already has, so a reader who clones the repo can follow the namespaces without first understanding a custom autoload setup. For a codebase whose job is partly to teach, the boring option wins.

Each module has the same internal shape:

```
app/Modules/Catalog/
├── CatalogServiceProvider.php
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   └── Seeders/
├── Filament/
│   └── Resources/
├── Models/
└── Policies/
```

Three pieces of wiring make that work, because Laravel does not discover any of it by default:

1. **Migrations.** Laravel only auto-loads `database/migrations`. Each module's service provider calls `loadMigrationsFrom(__DIR__.'/Database/Migrations')`, and the provider is registered in `bootstrap/providers.php`.
2. **Factories.** `Product::factory()` resolves to `Database\Factories\ProductFactory` by convention, which is wrong for a modular model. Models override `newFactory()` to point at the module's factory.
3. **Filament resources.** The panel provider gets one `discoverResources(in:, for:)` call per module, pointing at that module's `Filament/Resources` directory.

## Consequences

Adding a module means creating the directory, writing a service provider, registering it in `bootstrap/providers.php`, and adding a discovery line to the panel provider. That is four steps of ceremony that a single-directory app would not need. It is the price of the boundary, and it is paid once per module rather than once per class.

The boundary itself is enforced, not merely documented: a Pest architecture test asserts that no module references another module's namespace, and it runs in CI. Modules that need to cooperate do so through service interfaces or domain events in a later phase, never by importing each other's Eloquent models.

`app/Models/User.php` stays where Laravel put it. Authentication is framework concern, not domain, and moving it would fight the framework for no gain.
