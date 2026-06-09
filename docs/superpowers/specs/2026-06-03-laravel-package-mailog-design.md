# Laravel Package Mailog — Design

**Date:** 2026-06-03
**Status:** Approved

## Goal

Create `jiannius/mailog-package`: a clone-and-rename starting point for every future
jiannius Laravel **package** (a composer library consumed by host apps). It is the
package-world counterpart to the existing `jiannius/mailog-project` (which is a full
Laravel *application* mailog). The mailog ships with:

1. Proper AI guidelines (a `CLAUDE.md` for working *on* the package, plus consumer-facing
   guidelines that Laravel Boost merges into any host app that installs the package).
2. Laravel Boost genuinely installed and runnable — not just static guideline text.
3. A working Pest 4 + Orchestra Testbench test suite that is green out of the box.
4. A `configure.php` script that renames the placeholder identity to a real package.

## Background / context

Existing jiannius packages (`atom`, `filesystem`, `banner`, `permission`, `enquiry`) share
a common shape:

- `type: library`, PSR-4 `Jiannius\<Name>\` over `src/`, auto-registered service provider via
  `extra.laravel.providers`.
- `ServiceProvider::boot()` does `loadRoutesFrom` / `loadMigrationsFrom` /
  `loadViewsFrom(…, 'ns')` / `loadTranslationsFrom` / `Blade::anonymousComponentPath` /
  register commands + macros.
- `orchestra/testbench: ^11` as a dev dependency (modern packages).
- `authors`: TJ <tj@jiannius.com>, MIT, `config.allow-plugins.php-http/discovery: true`.

The most complete package, `atom`, additionally demonstrates patterns this mailog
generalizes: a singleton entry-point class (`Atom`, aliased `app('atom')`), an autoloaded
`src/Helpers.php`, an `Enum` trait for `FULL_UPPERCASE` backed enums, a publishable config,
and — crucially — **consumer-facing Boost guidelines** at
`resources/boost/guidelines/core.blade.php`.

Two facts discovered while exploring Laravel Boost (`laravel/boost` v2.4.8) drive the design:

- **Boost expects an `artisan`-capable app.** A library has none. Orchestra Testbench's
  `vendor/bin/testbench` binary (plus a workbench mailog app and `testbench.yaml`) provides
  that context, so Boost commands run as `vendor/bin/testbench boost:install` /
  `vendor/bin/testbench boost:mcp`.
- **A package ships consumer guidelines from `resources/boost/guidelines/`.** Boost's
  `DiscoverPackagePaths::resolveFirstPartyBoostPath()` reads
  `<package>/resources/boost/guidelines/*.blade.php` and merges `core.blade.php` (and
  `v<major>/…`) into the consuming app's `CLAUDE.md`. This is how `atom` teaches agents in
  host apps how to use it.

## Decisions (locked)

| Decision | Choice |
| --- | --- |
| Boost integration | Orchestra Testbench **workbench** → live Boost (`vendor/bin/testbench boost:*`) |
| Test framework | **Pest 4** + pest-plugin-laravel on Testbench |
| Clone/rename mechanism | **`configure.php`** interactive script (spatie-style), self-deleting |
| Generalization scope | **Lean structural mailog** — generalize atom's reusable bones, omit UI-specific machinery (TagCompiler, Vite/dist, editor casts, macro mixins) |

## Identity & placeholder tokens

Committed placeholder identity (all rewritten by `configure.php`):

- composer name: `jiannius/mailog`
- namespace: `Jiannius\Mailog\`
- service provider: `MailogServiceProvider`
- singleton class / container alias: `Mailog` / `mailog`
- helper function: `mailog()`
- view / translation / component namespace: `mailog`
- example model / table / command: `Mailog` / `mailogs` / `mailog:example`

`configure.php` prompts for: vendor, package slug, StudlyName (→ namespace + provider +
singleton + classes), author name, author email, description. It then find-replaces tokens
and renames files across the tree, and deletes itself.

## Directory layout

```
mailog-package/
├── .ai/
│   └── guidelines/
│       └── mailog.blade.php       # jiannius working-guidelines, merged into CLAUDE.md by boost:install
├── .claude/
│   └── settings.local.json          # permission allowlist
├── .cursor/
│   └── mcp.json                     # Boost MCP via testbench
├── .github/
│   └── workflows/
│       ├── tests.yml                # composer test (Pest + Testbench)
│       └── lint.yml                 # pint --test
├── components/
│   └── example.blade.php            # anonymous Blade component → <x-mailog::example> (standard Blade; no atom TagCompiler)
├── config/
│   └── mailog.php                 # publishable, mergeConfigFrom
├── database/
│   ├── factories/
│   │   └── MailogFactory.php
│   └── migrations/
│       └── 0001_01_01_000000_create_mailogs_table.php
├── docs/
│   └── superpowers/specs/           # this spec lives here
├── resources/
│   ├── boost/
│   │   └── guidelines/
│   │       └── core.blade.php       # CONSUMER-facing guidelines (Boost merges into host CLAUDE.md)
│   └── views/
│       └── .gitkeep
├── routes/
│   └── web.php                      # one example route
├── src/
│   ├── MailogServiceProvider.php  # full boot() wiring, every line commented to teach
│   ├── Mailog.php                 # app('mailog') singleton entry-point
│   ├── Helpers.php                  # autoloaded; mailog() helper
│   ├── Commands/
│   │   └── MailogCommand.php      # mailog:example
│   ├── Models/
│   │   └── Mailog.php             # ULID example model
│   └── Traits/
│       └── Enum.php                 # FULL_UPPERCASE backed-enum trait
├── tests/
│   ├── Pest.php
│   ├── TestCase.php                 # extends Orchestra\Testbench\TestCase
│   └── Feature/
│       └── MailogTest.php
├── workbench/                       # testbench workbench app (minimal, gives Boost an artisan)
│   └── (scaffolded by testbench: bootstrap/app providers as needed)
├── .editorconfig
├── .gitattributes                   # export-ignore dev files from dist tarball
├── .gitignore
├── .mcp.json                        # {"command":"vendor/bin/testbench","args":["boost:mcp"]}
├── boost.json                       # {agents:[claude_code], guidelines:true, mcp:true}
├── CHANGELOG.md
├── CLAUDE.md                        # Boost block + jiannius working guidelines + package architecture
├── composer.json
├── configure.php                    # interactive rename script (self-deletes)
├── LICENSE.md
├── phpunit.xml
└── README.md
```

## Component designs

### composer.json

- `type: library`, `name: jiannius/mailog`, `description`, MIT, authors TJ.
- `require`: `php: ^8.3`, `illuminate/support: ^13.0` (add `illuminate/database`, `illuminate/routing`
  only if the example needs them; keep lean).
- `require-dev`: `orchestra/testbench: ^11.0`, `pestphp/pest: ^4.0`,
  `pestphp/pest-plugin-laravel: ^4.0`, `laravel/boost: ^2.0`, `laravel/pint: ^1.0`.
- `autoload`: PSR-4 `Jiannius\\Mailog\\` → `src/`; `files: ["src/Helpers.php"]`.
- `autoload-dev`: `Jiannius\\Mailog\\Tests\\` → `tests/`; testbench `Workbench\\App\\`,
  `Workbench\\Database\\Factories\\`, `Workbench\\Database\\Seeders\\` as testbench expects.
- `extra.laravel.providers`: `["Jiannius\\Mailog\\MailogServiceProvider"]`.
- `scripts`:
  - `test`: `@php vendor/bin/testbench package:test` (or `vendor/bin/pest`).
  - `lint`: `vendor/bin/pint`.
  - `post-autoload-dump`: testbench `package:discover` (workbench wiring).
- `config.allow-plugins`: `pestphp/pest-plugin: true`, `php-http/discovery: true`.

### MailogServiceProvider (the generalization of atom's boot wiring)

- `register()`: `mergeConfigFrom(config/mailog.php, 'mailog')`; bind `Mailog` singleton and
  `$this->app->alias(Mailog::class, 'mailog')`.
- `boot()`, each line commented to teach the template user what it does:
  - `loadRoutesFrom(routes/web.php)`
  - `loadMigrationsFrom(database/migrations)`
  - `loadViewsFrom(resources/views, 'mailog')`
  - `loadTranslationsFrom(lang, 'mailog')` (lang dir optional; include commented if not shipped)
  - `Blade::anonymousComponentPath(components, 'mailog')`
  - register `MailogCommand` when `runningInConsole()`
  - `publishes()` for config + migrations under tag `mailog`
  - one example `Route` or rely on `routes/web.php`

### Mailog singleton + Helpers

- `Mailog.php`: a small entry-point object with one example method (e.g. `version()` or
  `config($key)`), demonstrating the `app('mailog')` pattern.
- `Helpers.php`: `if (! function_exists('mailog')) { function mailog() { return app('mailog'); } }`.

### Enum trait

Port atom's `Jiannius\Atom\Traits\Enum` shape generically (`all()`, `option()`, `label()`),
documented as the convention for `FULL_UPPERCASE` backed enums.

### Example model + migration + factory

- `Models/Mailog.php`: `HasUlids`, `$table = 'mailogs'`, a `data` json cast — demonstrates the
  jiannius main-table convention (ULID PK + json `data`).
- migration creates `mailogs` with `ulid('id')->primary()`, a `name` column, `json('data')`, timestamps.
- `MailogFactory.php`: a minimal factory used by the example test.

### Two guideline surfaces

1. **`CLAUDE.md`** — for working *on* the package. Composed of:
   - The Boost-generated block (`<laravel-boost-guidelines>…</laravel-boost-guidelines>`)
     produced by `vendor/bin/testbench boost:install` against the workbench app — foundation,
     boost, php, tests rules scoped to the package's installed packages.
   - The jiannius **Working Guidelines** (Think Before Coding / Simplicity First / Surgical
     Changes / Goal-Driven Execution), sourced from `.ai/guidelines/mailog.blade.php` so Boost
     re-merges them on every `boost:install`.
   - A hand-written **"What this is / Common commands / Architecture"** section describing the
     package mailog and the Testbench+Boost workflow (mirrors atom's and filesystem's CLAUDE.md style).
2. **`resources/boost/guidelines/core.blade.php`** — for *consumers*. A short template that
   teaches how to author the usage guidance Boost auto-merges into a host app's `CLAUDE.md`
   when the package is `composer require`d. Modelled on atom's `core.blade.php` (uses
   `@verbatim` around code snippets and `<code-snippet>` blocks), but with generic placeholder content.

### Boost / Testbench mechanics

- `testbench.yaml`: registers `Jiannius\Mailog\MailogServiceProvider` as a provider; sets a
  workbench app name; sqlite in-memory for the workbench. This is what gives Boost an artisan-capable app.
- `.mcp.json` and `.cursor/mcp.json`: `{ "mcpServers": { "laravel-boost": { "command":
  "vendor/bin/testbench", "args": ["boost:mcp"] } } }`.
- `boost.json`: `{ "agents": ["claude_code"], "cloud": false, "guidelines": true, "mcp": true,
  "packages": [] }`.

### Tests (Pest 4 + Testbench)

- `tests/TestCase.php`: `abstract class TestCase extends Orchestra\Testbench\TestCase`, uses
  `RefreshDatabase`, `getPackageProviders()` → `[MailogServiceProvider::class]`,
  `defineEnvironment()` sets sqlite `:memory:` + app key.
- `tests/Pest.php`: `pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit')`.
- `tests/Feature/MailogTest.php`: asserts (a) the migration runs and a `Mailog` can be
  created via factory, (b) `app('mailog')` resolves the singleton, (c) `config('mailog.*')`
  loads. Green out of the box.
- `phpunit.xml`: Feature/Unit testsuites, `APP_ENV=testing`, `DB_CONNECTION=testing`.

### configure.php

Pure-PHP interactive script (no dependencies), run as `php configure.php`:

1. Prompt: vendor (default `jiannius`), package slug, StudlyName, author name, author email,
   description. Derive namespace, provider, singleton, helper, table from StudlyName/slug.
2. Walk the tree (excluding `.git`, `vendor`, `node_modules`); replace placeholder tokens in file
   contents; rename files whose names contain `Mailog`/`mailog`.
3. Print next steps (`composer install`, `vendor/bin/testbench boost:install`, `composer test`).
4. Delete `configure.php` itself.

### README.md

Documents the clone-and-go flow:

```bash
git clone <repo> my-package && cd my-package
php configure.php            # rename to your package
composer install
vendor/bin/testbench boost:install   # generate / refresh CLAUDE.md Boost block
composer test                # Pest + Testbench, green out of the box
```

Explains the two guideline surfaces and the Testbench-as-artisan model.

### Dotfiles

- `.gitignore`: `/vendor`, `/node_modules`, `.phpunit.result.cache`, `/.phpunit.cache`,
  `build/`, `.env`, IDE dirs — mirrors filesystem package.
- `.gitattributes`: `export-ignore` for tests, docs, workbench, configure.php, CI, dotfiles so
  the published tarball is lean.
- `.editorconfig`: 4-space PHP, 2-space yaml — mirrors existing packages.
- `LICENSE.md` (MIT, TJ), `CHANGELOG.md` (Keep-a-Changelog stub).

## Implementation caveat

Generating the real Boost block in `CLAUDE.md` requires `composer install` +
`vendor/bin/testbench boost:install`, which need network/composer access. If unavailable in the
build environment, the `CLAUDE.md` Boost block will be hand-authored (adapted from
`mailog-project`'s proven output) and a note added that `vendor/bin/testbench boost:install`
regenerates it. The committed mailog is complete either way.

## Out of scope (YAGNI)

- Blade TagCompiler / `<atom:…>` precompiler.
- Vite/dist front-end asset pipeline.
- Editor-image casts, the full macro-mixin set, Actions/Mail/Events/Services subsystems.
- A published Packagist release or `composer create-project` distribution (git-clone +
  `configure.php` is the primary path).
```

## Success criteria

- `composer install` succeeds; `composer test` is green.
- `vendor/bin/testbench boost:mcp` starts the Boost MCP server.
- `vendor/bin/testbench boost:install` (re)generates the `CLAUDE.md` Boost block.
- A host app that `composer require`s the (renamed) package and lists it in its `boost.json`
  picks up `resources/boost/guidelines/core.blade.php`.
- `php configure.php` renames the placeholder identity end-to-end and self-deletes.
