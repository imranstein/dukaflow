# Changelog

All notable changes to this project are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions before 1.0.0 follow the project's own phase numbering rather than semver, since there isn't a stable public API yet to version against.

## [Unreleased]

Phase 4: polish and launch readiness, toward `v1.0.0`.

### Added
- Architecture overview, domain glossary, and a sync deep dive under `Docs/`.
- `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, issue and PR templates.
- This changelog.
- A production Docker setup for self-hosters: `docker/Dockerfile`, `docker/nginx.conf`, `docker/entrypoint.sh`, `docker-compose.prod.yml`. CI now builds the image on every push.
- Env-driven trusted-proxy support (`TRUSTED_PROXIES`) for running behind a reverse proxy.

### Fixed
- A review of the new Docker setup caught a real build blocker (the composer stage failing on a missing `ext-intl`) and several production-readiness gaps — unguarded `APP_KEY`/`DB_PASSWORD`, no trusted-proxy config, a root/www-data file-ownership edge case in the entrypoint. All closed.

## [v0.4.0-beta] — 2026-08-12

Phase 3: the rep PWA and offline sync — the centrepiece.

### Added
- ADR-002 (offline sync strategy) and ADR-003 (id strategy), written before any sync code.
- A `Sync` module: idempotent push for orders and visit outcomes, cursor-based delta pull scoped to the requesting rep, a per-rep pre-resolved pricebook, price-variance flagging, conflicts recorded rather than silently merged.
- Two shared-kernel contracts, `OrderIntake` and `VisitOutcomeIntake`, so Sync never touches Orders' or Distribution's models directly.
- A hand-written PWA at `/rep`: its own login, a service worker, a per-rep-namespaced IndexedDB layer, and an Alpine.js capture flow with zero server round-trips per interaction. An upload queue with retry and backoff.
- Visit outcomes (no-sale, with a reason) as their own record in Distribution.
- CI now builds and checks the frontend on every push.

### Fixed
A strict read-only review found 21 candidate defects across five dimensions; 19 were distinct after duplicates, 18 fixed. The two that mattered most:
- The pull endpoint answered every rep with every customer, route and visit schedule in the whole distributor, not just their own round — now scoped to the requesting rep at the contract level.
- The entity write and the audit-log row that makes a push replayable were two separate statements; a crash between them left an order that could never be successfully resubmitted. Now one transaction.

Also: an ownership check so a device can only act for customers on its own rep's route, per-entity error isolation so one malformed entity can't fail a whole batch, sanitized error messages, a price-variance flag that's now actually visible in the back office, a login rate limit, and two missing tests for the phase's own acceptance criterion.

## [v0.3.0-beta] — 2026-08-09

Phase 2: orders and inventory.

### Added
- ADR-004 (money handling) and the `Money` value object — integer minor units, never floats.
- Order capture with lines priced from the correct list and snapshotted at the time of sale.
- A guarded order lifecycle (draft → submitted → approved → fulfilled, or cancelled) that throws on an illegal transition rather than failing silently. ADR-005.
- Cash and credit payment records — no gateways, by design.
- Warehouses, van stock loading, and an append-only stock ledger: a balance is the sum of every movement, never an edited column. ADR-006.
- End-of-day reconciliation that reports variances and writes the adjustments on close.
- Dashboards: orders by route, stock position, reconciliation variances.
- MySQL joined the CI matrix alongside SQLite, ahead of the stock ledger, since its invariants are exactly the transaction and constraint behaviour the two databases diverge on.

### Fixed
A review pass found six defects, all the same shape — a guard in the right place watching the wrong thing: a Filament form that let a draft be saved as fulfilled with no stock movement behind it, quantity/removal actions that didn't check a line belonged to the order they were called on, a currency-code case mismatch that rejected valid prices, an order left marked fulfilled in memory after its transaction rolled back, and order references that would collide past the hundred-thousandth order of a year.

## [v0.2.0-alpha] — 2026-08-08

Phase 1: catalog and distribution.

### Added
- Products, units of measure, price lists with effective dates, and price list assignment by customer or route — narrowest scope wins.
- Outlets with geolocation, sales reps, routes/beats, visit schedules.
- Filament resources for all of it, grouped by module.
- Admin, manager and rep roles behind a shared back-office policy.
- Realistic demo seed data: an Ethiopian FMCG catalogue, four beats, eighteen outlets.

## [v0.1.0-alpha] — 2026-08-08

Phase 0: foundation.

### Added
- Laravel 13 on PHP 8.3, Filament 5, Livewire 4.
- Pint, Larastan (level 6) and Pest wired into GitHub Actions CI.
- ADR-001: module boundaries (`app/Modules/`, enforced by architecture tests).
- A walking skeleton: a `Product` model in Catalog with its own migration, factory, Filament resource, and one Pest feature test, proving the module conventions end to end.
- AGPL-3.0 license, initial README, roadmap.

[Unreleased]: https://github.com/imranstein/dukaflow/compare/v0.4.0-beta...HEAD
[v0.4.0-beta]: https://github.com/imranstein/dukaflow/compare/v0.3.0-beta...v0.4.0-beta
[v0.3.0-beta]: https://github.com/imranstein/dukaflow/compare/v0.2.0-alpha...v0.3.0-beta
[v0.2.0-alpha]: https://github.com/imranstein/dukaflow/compare/v0.1.0-alpha...v0.2.0-alpha
[v0.1.0-alpha]: https://github.com/imranstein/dukaflow/releases/tag/v0.1.0-alpha
