# Decisions

A lightweight decision log. Anything architectural graduates to a formal ADR in `Docs/adr/` once code exists (see source of truth, section 4). This file is for the smaller calls that still shouldn't get relitigated.

| Date | Decision | Why |
|------|----------|-----|
| 2026-08-08 | ~~`Docs/` holds planning docs and a separate lowercase `docs/` holds code docs and ADRs~~ — **reversed**. One `Docs/` directory holds everything, with ADRs in `Docs/adr/` | A repo cannot contain both `Docs/` and `docs/`. macOS and Windows checkouts are case-insensitive, so the two silently merge — which is exactly what happened: the ADRs written to `docs/adr/` landed in `Docs/adr/`, and every link pointing at the lowercase path was broken on GitHub while working fine locally |
| 2026-08-08 | Livewire 4, not Livewire 3 as originally written in the source of truth | Filament 5 requires `livewire/livewire ^4.1`. The brief asks for Filament "latest stable", and that phrase is the one worth keeping for a portfolio piece, so the Livewire major moves with it. Source of truth section 3 amended |
| 2026-08-08 | PHP 8.3 with Pest 4, rather than PHP 8.4 with Pest 5 | Pest 5 and `pest-plugin-laravel` 5 both require PHP ^8.4. The brief specifies PHP 8.3+, and the whole stack (Laravel 13.24, Filament 5.7, Livewire 4.3, Pest 4.7, Larastan 3.10) resolves cleanly on 8.3. CI pins 8.3 so it matches the lockfile |
| 2026-08-08 | `tests/Arch` is excluded from Larastan analysis | Pest's `arch()` DSL resolves through `__call` at runtime and cannot be modelled statically. The arch tests still run in the Pest suite in CI, so the rules are enforced — just not by PHPStan |
