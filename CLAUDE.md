# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jiannius/mailog` is a **Laravel package mailog** — a clone-and-rename starting point for jiannius Laravel packages (composer `type: library`, PSR-4 `Jiannius\Mailog\`). It is consumed by host Laravel apps via `composer require`; this repo is the library itself, not a host app.

Because a package has no `artisan` binary, all dev/test/AI tooling runs through **Orchestra Testbench**: `vendor/bin/testbench` is the artisan equivalent, booting a throwaway Laravel 13 app (configured by `testbench.yaml`) with `MailogServiceProvider` registered.

Main stack — abide by these versions: php 8.4 (constraint `^8.3`) · laravel/framework v13 (via Testbench) · orchestra/testbench v11 · pestphp/pest v4 · phpunit v12 · laravel/pint v1 · laravel/boost v2.

To turn this mailog into a real package, run `php configure.php` (see README) — it rewrites the `Mailog`/`mailog` placeholder identity and self-deletes.

## Common commands

```bash
composer install                                   # install dependencies
composer test                                      # Pest 4 + Testbench suite
vendor/bin/pest tests/Feature/SmokeTest.php        # single test file
vendor/bin/pest --filter='binds the mailog singleton'  # single test
composer lint                                      # vendor/bin/pint
vendor/bin/testbench boost:mcp                     # start the Boost MCP server (used by editors)
vendor/bin/testbench boost:install                 # merge Boost core + .ai guidelines (review output)
vendor/bin/testbench serve                         # boot the throwaway Testbench app for manual checks
```

## Laravel Boost

Laravel Boost is installed (dev) and runs through Testbench. Editors connect via `.mcp.json` / `.cursor/mcp.json`, which invoke `vendor/bin/testbench boost:mcp`.

- Use Boost's `search-docs` tool before changing code that touches Laravel APIs — it returns version-specific docs for the installed packages.
- Searching: use multiple broad, topic-based queries (`['validation rules', 'custom validation']`); use `"quoted phrases"` for exact position matching; don't put package names in queries (package info is already shared).
- Custom guidelines for this repo live in `.ai/guidelines/*.blade.php`. `vendor/bin/testbench boost:install` can merge them with Boost's core guidelines — but Boost's core rules assume a host app with `artisan`, so this CLAUDE.md carries the package-adapted versions (see Development guidelines below); review any regenerated block before committing it.

## Testbench is the artisan

- Run artisan commands as `vendor/bin/testbench <command>` (e.g. `vendor/bin/testbench route:list`, `vendor/bin/testbench tinker --execute='...'`). There is no `php artisan` here.
- Do NOT use `make:` generators — they scaffold into the throwaway Testbench app, not this package. Create package files manually under `src/`, following the existing structure and namespaces (check sibling files first).

## Two guideline surfaces

1. **This `CLAUDE.md`** guides work *on the package itself*.
2. **`resources/boost/guidelines/core.blade.php`** ships *to consuming apps*: when a host app installs this package and lists it in its own `boost.json` `packages`, Boost merges that file into the host's `CLAUDE.md`. Keep it as usage guidance for someone building *with* the package.

## Architecture

### Service-provider wiring (`src/MailogServiceProvider.php`)

`register()` merges `config/mailog.php` and binds the `Mailog` singleton (aliased `app('mailog')`). `boot()` is minimal by default: it only publishes the config (tag `mailog-config`, console-only). Hooks for routes, migrations, views, components, translations, and commands sit there commented out — uncomment one and create the matching file/directory when the package needs it (the README "How-to recipes" carry the file templates). Read this file first when something seems to come from nowhere.

### Singleton entry-point (`src/Mailog.php` → `app('mailog')` / `mailog()`)

The package's public API object, resolvable via the container alias `mailog` or the autoloaded `mailog()` helper (`src/Helpers.php`). Add cross-cutting package methods here.

## Development guidelines

Curated from the jiannius app mailog (`mailog-project`) — the subset that applies to package development.

### Conventions

- Follow the existing code conventions; when creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods (`isRegisteredForDiscounts`, not `discount()`).
- Stick to the existing directory structure — don't create new base folders without approval.
- Don't change the package's dependencies without approval.
- Only create documentation files if explicitly requested.
- Be concise in explanations — focus on what's important rather than obvious details.

### PHP style

- Always use curly braces for control structures, even single-line bodies.
- Use PHP 8 constructor property promotion (`public function __construct(public GitHub $github) {}`); no empty zero-parameter constructors unless private.
- Explicit return types and type hints on all parameters: `function isAccessible(User $user, ?string $path = null): bool`.
- Prefer PHPDoc over inline comments; every public/private method gets a one-line PHPDoc. Use array-shape definitions in PHPDoc where useful.
- Backed enums mix in `Jiannius\Mailog\Traits\Enum`; cases are `FULL_UPPERCASE`. The trait provides `all()`, `option()`, `label()`, `get()`, `is()`/`isNot()`.

### Models & data

- Main tables use ULID primary keys (`HasUlids` + `$table->ulid('id')->primary()`) plus a nullable `data` json column for metadata. Plain auto-increment ids are fine for pivot tables.
- When adding a model, add its factory (and a seeder if useful) and wire `newFactory()` — package factory namespaces aren't auto-discovered by Laravel's convention.
- Use named routes and `route()` when generating links.

### Testing (Pest 4 + Testbench)

- Every change must be programmatically tested: write or update a Pest test in `tests/Feature` or `tests/Unit` (both extend `Tests\TestCase` — Orchestra Testbench, in-memory sqlite), then run the affected tests.
- Don't write one-off verification scripts or tinker probes when a test can prove the behavior — tests are the source of truth.
- Run the minimum tests needed: `vendor/bin/pest --filter='name'` or a single file; `composer test` for the full suite.
- In tests, build models with factories; check for custom factory states before configuring manually. Use `fake()->word()`-style faker calls.
- There is no `make:test` — create test files manually under `tests/Feature` or `tests/Unit` (Pest auto-binds `Tests\TestCase` via `tests/Pest.php`).
- Do NOT delete tests without approval.

### Code style (Pint)

- After modifying PHP files, run `vendor/bin/pint --dirty` (or `composer lint`) before finalizing changes — run it to fix, not just `--test` to check.

### Workflow

- Always squash-merge when exiting a worktree, then remove the worktree.
- Plan mode: no need to use the superpowers skills.
- Session close: when closing or clearing the session, save important gotchas/findings to memory and clear any stale data pieces from it.

## Working Guidelines

Behavioral guidelines to reduce common LLM coding mistakes. For trivial tasks, use judgment.

### 1. Think Before Coding

Don't assume. Don't hide confusion. Surface tradeoffs. State assumptions explicitly; if multiple interpretations exist, present them rather than picking silently. If something is unclear, stop, name what's confusing, and ask.

### 2. Simplicity First

Minimum code that solves the problem. No features beyond what was asked, no abstractions for single-use code, no "configurability" that wasn't requested, no error handling for impossible scenarios.

### 3. Surgical Changes

Touch only what you must. Don't "improve" adjacent code or refactor things that aren't broken. Match existing style. Remove imports/variables your change orphaned; leave pre-existing dead code unless asked.

### 4. Goal-Driven Execution

Transform the task into a verifiable goal ("write a test that reproduces the bug, then make it pass") and loop until verified.

### 5. Caveman

Talk normally in discussion; talk like a caveman (caveman skill) during coding work.
