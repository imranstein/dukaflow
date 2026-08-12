## What this does

A short description of the change and why it's needed. Link the issue it closes, if there is one.

## Checklist

- [ ] `./vendor/bin/pint` — clean
- [ ] `./vendor/bin/phpstan analyse` — clean, no new `@phpstan-ignore` or baseline entries
- [ ] `./vendor/bin/pest` — green, including a direct test of any domain logic this touches (not just an HTTP smoke test)
- [ ] No module in `app/Modules/` imports another module's models, queries its tables, or names its classes in a string — cross-module needs go through the shared kernel
- [ ] No new package without a reason stated below, and no sync/offline/storage package regardless of reason
- [ ] Docs updated if this changes behaviour a doc already describes — an ADR if the change involved a real decision, `Docs/memory-bank/` if it's smaller

## New dependency? (delete this section if not)

What it is, and why the standard library / an already-installed package couldn't do it.

## Anything a reviewer should look at closely

Module boundaries, migrations, and anything touching the sync layer get read carefully. Flag it here if this PR is one of those, and point at the specific thing you're least sure about.
