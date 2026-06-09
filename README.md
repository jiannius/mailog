# Jiannius Mailog Package

A clone-and-rename starting point for jiannius Laravel **packages**. Ships with Laravel Boost (live, via Orchestra Testbench), a Pest 4 test suite that is green out of the box, dual AI-guideline surfaces, and a one-shot `configure.php` rename script.

The mailog is deliberately **minimal**: it wires only what every package needs (config merge, a singleton entry-point, a helper, tests, CI). Routes, migrations, models, commands, views, and components are *recipes* (below) plus commented-out hooks in the service provider — nothing to delete before you start building.

This is the package-world counterpart to `jiannius/mailog-project` (the Laravel app mailog).

## Requirements

- PHP 8.3+ and Composer
- Everything else (Laravel 13, Testbench 11, Pest 4, Pint, Boost 2) installs as dependencies

## Creating a new package

> This section describes turning the mailog into a real package — delete it from your new package's README once you're done.

```bash
git clone https://github.com/jiannius/mailog-package.git my-package
cd my-package
rm -rf .git && git init                # start your package with fresh history
php configure.php                      # interactive rename; self-deletes when done
composer install
composer test                          # green out of the box
```

`configure.php` prompts for (defaults in parentheses):

| Prompt | Default | Drives |
| --- | --- | --- |
| Vendor | `jiannius` | composer name, namespace vendor |
| Package name (slug) | `mailog` | composer name, config key, view/component namespace, command prefix |
| StudlyCase name | derived from slug | namespace, class names (`BannerServiceProvider`, `Banner`, …) |
| Author name / email | `TJ` / `tj@jiannius.com` | composer authors |
| Description | `Jiannius <Name>` | composer description |

It rewrites every placeholder token in file **contents** (`jiannius/mailog`, `Jiannius\Mailog`, the JSON-escaped `Jiannius\\Mailog`, `Mailog`, `mailog`) and **renames files** whose names contain the placeholder (`MailogServiceProvider.php` → `BannerServiceProvider.php`, `config/mailog.php` → `config/banner.php`, …), then deletes itself. The renamed package boots and passes its whole suite immediately.

Finally, point the repo at your own remote:

```bash
git add -A && git commit -m "init from jiannius/mailog-package"
git remote add origin git@github.com:jiannius/<your-package>.git
git push -u origin main
```

## Development

### There is no `artisan` — Testbench is the artisan

A package is not an app, so it has no `artisan` binary. Orchestra Testbench's `vendor/bin/testbench` boots a throwaway Laravel app (configured by `testbench.yaml`, which registers this package's service provider) — so artisan commands, tests, and Laravel Boost all run inside a real app context.

```bash
composer test                                   # full Pest suite
vendor/bin/pest tests/Feature/SmokeTest.php     # one file
vendor/bin/pest --filter='binds the mailog'   # one test
composer lint                                   # Pint (vendor/bin/pint)
vendor/bin/testbench route:list                 # any artisan command
vendor/bin/testbench tinker --execute='mailog()->version();'
vendor/bin/testbench serve                      # boot the throwaway app in a browser
```

Don't use `make:` generators — they scaffold into the throwaway Testbench app, not your package. Create files manually under `src/` following the how-to recipes below.

### Tests

Tests live in `tests/Feature` and `tests/Unit`, run on Pest 4, and extend `Tests\TestCase` (Orchestra Testbench + in-memory sqlite — wired automatically via `tests/Pest.php`). The base `TestCase` ships without `RefreshDatabase`; the model recipe below adds it when your package gains migrations.

### Code style

Pint with Laravel defaults. Run `composer lint` (or `vendor/bin/pint --dirty`) after changing PHP files. CI runs both the suite (`.github/workflows/tests.yml`) and style check (`.github/workflows/lint.yml`) on pushes/PRs to `main`.

## Laravel Boost

Boost is installed as a dev dependency and runs through Testbench:

- **MCP server** — `.mcp.json` (Claude Code) and `.cursor/mcp.json` (Cursor) launch `vendor/bin/testbench boost:mcp`. Your editor will ask once to approve the `laravel-boost` MCP server; after that, tools like `search-docs` (version-specific Laravel docs), `database-schema`, and `tinker` are available while working on the package.
- **`vendor/bin/testbench boost:install`** — merges Boost's core guidelines with this repo's `.ai/guidelines/`. Note that Boost's core rules assume a host *app* with `artisan`, so `CLAUDE.md` carries package-adapted versions; review any regenerated block before committing it.

## AI guidelines — two surfaces

1. **`CLAUDE.md`** — guidance for agents working **on this package**: Testbench-as-artisan rules, PHP/test/style conventions (curated from `mailog-project`), and the architecture map. The same content lives in `.ai/guidelines/mailog.blade.php` so `boost:install` can merge it.
2. **`resources/boost/guidelines/core.blade.php`** — guidance shipped **to consuming apps**. When a host app installs your package and lists it in its own `boost.json` `"packages"` array, Boost merges this file into the host app's `CLAUDE.md`. Keep it as copy-paste-ready usage guidance for someone building *with* your package (the included file shows the format — `@verbatim` + `<code-snippet>` blocks). Update it as your package grows real features.

## How-to recipes

Each optional piece is a commented-out hook in `MailogServiceProvider::boot()` plus the steps below: create the file(s), uncomment the hook.

### Add a config value

Add the key to `config/mailog.php`. It's merged in `register()` so `config('mailog.*')` always works; host apps can override after `php artisan vendor:publish --tag=mailog-config`. Read it anywhere via `mailog()->config('key')`.

### Add a model (+ migration + factory)

Conventions: ULID primary key, a nullable `data` json column for metadata, and a `newFactory()` override (package factory namespaces aren't auto-discovered by Laravel).

1. Create the trio:

```php
// src/Models/Thing.php
namespace Jiannius\Mailog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jiannius\Mailog\Database\Factories\ThingFactory;

class Thing extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['name', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    protected static function newFactory(): ThingFactory
    {
        return ThingFactory::new();
    }
}
```

```php
// database/migrations/0001_01_01_000000_create_things_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('things', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('things');
    }
};
```

```php
// database/factories/ThingFactory.php
namespace Jiannius\Mailog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jiannius\Mailog\Models\Thing;

class ThingFactory extends Factory
{
    protected $model = Thing::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'data' => []];
    }
}
```

2. Register the factory namespace in `composer.json` (then `composer dump-autoload`):

```json
"autoload": {
    "psr-4": {
        "Jiannius\\Mailog\\": "src/",
        "Jiannius\\Mailog\\Database\\Factories\\": "database/factories/"
    }
}
```

3. Uncomment `loadMigrationsFrom` in the service provider — host apps pick the migrations up with plain `php artisan migrate`.

4. Add `use RefreshDatabase;` (from `Illuminate\Foundation\Testing`) to `tests/TestCase.php` so tests run the package's migrations against in-memory sqlite.

### Add an artisan command

Create the class, then uncomment the `commands([...])` call in `MailogServiceProvider::boot()` and list it. Prefix signatures with your package slug.

```php
// src/Commands/ExampleCommand.php
namespace Jiannius\Mailog\Commands;

use Illuminate\Console\Command;

class ExampleCommand extends Command
{
    protected $signature = 'mailog:example';

    protected $description = 'Example command.';

    public function handle(): int
    {
        $this->info('Mailog v'.mailog()->version());

        return self::SUCCESS;
    }
}
```

### Add a route

Create `routes/web.php`, then uncomment `loadRoutesFrom`. Routes load automatically into every host app, so keep them named and prefixed:

```php
use Illuminate\Support\Facades\Route;

Route::get('/mailog', fn () => response()->json([
    'package' => 'mailog',
    'version' => mailog()->version(),
]))->name('mailog.index');
```

### Add a Blade component

Create `components/`, then uncomment `anonymousComponentPath` (and import the `Blade` facade). `components/card.blade.php` becomes `<x-mailog::card>` in host apps:

```blade
{{-- components/card.blade.php --}}
@props(['title' => 'Mailog'])

<div {{ $attributes->merge(['class' => 'mailog-card']) }}>
    <h2>{{ $title }}</h2>
    {{ $slot }}
</div>
```

### Add views or translations

Views: create `resources/views/`, uncomment `loadViewsFrom`, render as `view('mailog::name')`. Translations: create `lang/`, uncomment `loadTranslationsFrom`, use `__('mailog::file.key')`.

### Add to the public API

Add methods to `src/Mailog.php` — the singleton behind `app('mailog')` and the autoloaded `mailog()` helper. This is the package's front door; keep cross-cutting operations here rather than scattering static helpers.

### Add an enum

Mix `Jiannius\Mailog\Traits\Enum` into a backed enum with `FULL_UPPERCASE` cases — you get `all()`, `option()`, `label()`, `get()`, `is()`/`isNot()` (see `tests/Unit/EnumTest.php` for the full surface).

### Add a test

Create a file under `tests/Feature` or `tests/Unit` — Pest binds `Tests\TestCase` automatically, no class boilerplate needed:

```php
it('does the thing', function () {
    expect(mailog()->config('name'))->toBe('Mailog');
});
```

## Using your package in a host app

Until it's on Packagist, require it via a VCS or path repository:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/jiannius/<your-package>" }
    ],
    "require": {
        "jiannius/<your-package>": "dev-main"
    }
}
```

The service provider auto-registers (`extra.laravel.providers`), config publishes with `php artisan vendor:publish --tag=mailog-config`, and the `mailog()` singleton is immediately available. To pull the package's AI guidelines into the app's `CLAUDE.md`, add the package name to the app's `boost.json` `"packages"` array and re-run `php artisan boost:install`.

## What's inside

| Path | Purpose |
| --- | --- |
| `src/MailogServiceProvider.php` | Config merge + singleton binding; commented-out hooks for routes, migrations, views, components, translations, commands |
| `src/Mailog.php` | Singleton entry-point — `app('mailog')` / `mailog()` |
| `src/Helpers.php` | Autoloaded `mailog()` helper |
| `src/Traits/Enum.php` | `FULL_UPPERCASE` backed-enum trait |
| `config/mailog.php` | Publishable config (tag `mailog-config`) |
| `resources/boost/guidelines/core.blade.php` | Consumer-facing Boost guidelines |
| `.ai/guidelines/mailog.blade.php` | Package-dev guidelines (Boost-merge source for `CLAUDE.md`) |
| `.mcp.json` / `.cursor/mcp.json` / `boost.json` | Laravel Boost wiring (via Testbench) |
| `testbench.yaml` | Registers the provider into the Testbench app |
| `tests/` | Pest 4 + Testbench suite |
| `configure.php` | One-shot rename script (deletes itself) |

## License

MIT.
