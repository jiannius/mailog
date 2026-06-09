# Laravel Package Mailog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `jiannius/mailog-package`: a clone-and-rename starting point for jiannius Laravel packages, shipping live Laravel Boost (via Orchestra Testbench), a green Pest 4 test suite, dual AI-guideline surfaces, and a `configure.php` rename script.

**Architecture:** A `type: library` composer package (`Jiannius\Mailog\` over `src/`). A package has no `artisan`, so all dev/test/Boost tooling runs through Orchestra Testbench's `vendor/bin/testbench` binary, which boots a throwaway Laravel 13 app (configured by `testbench.yaml`) with the package's service provider registered. The package exposes the atom-generalized bones: a service provider that wires routes/migrations/views/components, a `Mailog` singleton entry-point (`app('mailog')`), an autoloaded `mailog()` helper, the `Enum` trait, a ULID example model, and a publishable config.

**Tech Stack:** PHP 8.4 (constraint `^8.3`), Laravel 13 (via `illuminate/support ^13`), Orchestra Testbench `^11`, Pest `^4` + pest-plugin-laravel `^4`, Laravel Boost `^2`, Laravel Pint `^1`.

**Environment confirmed:** `php 8.4.21`, `composer 2.9.5`, packagist reachable — so `composer install`, `composer test`, and `vendor/bin/testbench boost:mcp` all run for real during execution.

**Placeholder identity** (rewritten by `configure.php`): composer `jiannius/mailog` · namespace `Jiannius\Mailog` · provider `MailogServiceProvider` · singleton/alias `Mailog`/`mailog` · helper `mailog()` · view/component namespace `mailog` · model/table/command `Mailog`/`mailogs`/`mailog:example`.

**Working directory:** `/Users/tj/Projects/jiannius/mailog-package` (git already initialized on `main`; spec committed under `docs/superpowers/specs/`).

---

## Note on TDD shape

This is a scaffold: many files interlock before anything boots. Task 1 lays the manifest + Testbench config and installs deps; Task 2 proves the test harness boots; Tasks 3–6 are strict TDD (failing Pest test → minimal implementation → green). Pure config/doc files (Boost wiring, CLAUDE.md, CI, configure.php) are created then verified with an explicit command.

---

## Task 1: Composer manifest, Testbench config, and project dotfiles

**Files:**
- Create: `composer.json`
- Create: `testbench.yaml`
- Create: `.gitignore`
- Create: `.gitattributes`
- Create: `.editorconfig`
- Create: `LICENSE.md`
- Create: `CHANGELOG.md`
- Create: `README.md` (placeholder; finalized in Task 11)

- [ ] **Step 1: Create `composer.json`**

```json
{
    "name": "jiannius/mailog",
    "description": "Jiannius Laravel package mailog",
    "type": "library",
    "keywords": ["laravel", "package", "mailog"],
    "license": "MIT",
    "authors": [
        {
            "name": "TJ",
            "email": "tj@jiannius.com"
        }
    ],
    "require": {
        "php": "^8.3",
        "illuminate/support": "^13.0"
    },
    "require-dev": {
        "laravel/boost": "^2.0",
        "laravel/pint": "^1.0",
        "orchestra/testbench": "^11.0",
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-laravel": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Jiannius\\Mailog\\": "src/",
            "Jiannius\\Mailog\\Database\\Factories\\": "database/factories/"
        },
        "files": [
            "src/Helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Jiannius\\Mailog\\Tests\\": "tests/",
            "Workbench\\App\\": "workbench/app/",
            "Workbench\\Database\\Factories\\": "workbench/database/factories/",
            "Workbench\\Database\\Seeders\\": "workbench/database/seeders/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Jiannius\\Mailog\\MailogServiceProvider"
            ]
        }
    },
    "scripts": {
        "post-autoload-dump": "@php vendor/bin/testbench package:discover --ansi",
        "test": "@php vendor/bin/pest",
        "lint": "@php vendor/bin/pint"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Create `testbench.yaml`** (registers the package provider into the Testbench app so Boost and tests discover it)

```yaml
providers:
  - Jiannius\Mailog\MailogServiceProvider
```

- [ ] **Step 3: Create `.gitignore`**

```
/vendor
/node_modules
/build
.phpunit.result.cache
/.phpunit.cache
.env
.env.backup
/auth.json
/.idea
/.vscode
/.zed
/.fleet
.DS_Store
```

- [ ] **Step 4: Create `.gitattributes`** (keep the published tarball lean)

```
* text=auto eol=lf

/.github            export-ignore
/tests              export-ignore
/workbench          export-ignore
/docs               export-ignore
/.ai                export-ignore
.editorconfig       export-ignore
.gitattributes      export-ignore
.gitignore          export-ignore
.mcp.json           export-ignore
.cursor             export-ignore
boost.json          export-ignore
configure.php       export-ignore
phpunit.xml         export-ignore
testbench.yaml      export-ignore
CHANGELOG.md        export-ignore
```

- [ ] **Step 5: Create `.editorconfig`**

```
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 4
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{yml,yaml}]
indent_size = 2
```

- [ ] **Step 6: Create `LICENSE.md`**

```
MIT License

Copyright (c) Jiannius

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

- [ ] **Step 7: Create `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

- Initial package mailog.
```

- [ ] **Step 8: Create placeholder `README.md`**

```markdown
# Jiannius Mailog

Laravel package mailog. See the implementation plan; finalized in Task 11.
```

- [ ] **Step 9: Install dependencies**

Run: `cd /Users/tj/Projects/jiannius/mailog-package && composer install`
Expected: Composer resolves and installs `orchestra/testbench`, `pestphp/pest`, `laravel/boost`, `laravel/pint`, and Laravel 13 packages. The `post-autoload-dump` `package:discover` runs (may print "Discovered Package" lines or nothing custom). No fatal error.

Note: if `package:discover` errors on the very first install because no classes exist yet, re-run `composer install` after Task 3 — the script is idempotent. It should succeed here because `testbench.yaml` is present.

- [ ] **Step 10: Validate the manifest**

Run: `composer validate --no-check-publish`
Expected: `./composer.json is valid`

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "chore: composer manifest, testbench config, and project dotfiles"
```

---

## Task 2: Pest + Testbench harness with a boot smoke test

**Files:**
- Create: `phpunit.xml`
- Create: `tests/TestCase.php`
- Create: `tests/Pest.php`
- Create: `tests/Feature/SmokeTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Feature/SmokeTest.php`

```php
<?php

it('boots the testbench application in the testing environment', function () {
    expect(app())->not->toBeNull();
    expect(app()->environment())->toBe('testing');
});
```

- [ ] **Step 2: Create `tests/TestCase.php`** (Orchestra base; in-memory sqlite)

```php
<?php

namespace Jiannius\Mailog\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jiannius\Mailog\MailogServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Register the package's service provider(s) into the test application.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MailogServiceProvider::class];
    }

    /**
     * Configure the Testbench environment (in-memory sqlite + app key).
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
```

- [ ] **Step 3: Create `tests/Pest.php`** (bind the base TestCase to both suites)

```php
<?php

use Jiannius\Mailog\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
```

- [ ] **Step 4: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="testing"/>
    </php>
</phpunit>
```

- [ ] **Step 5: Run the suite to verify it passes**

Run: `composer test`
Expected: PASS — 1 passed (2 assertions). Pest discovers `tests/Feature/SmokeTest.php`, boots the Testbench app with `MailogServiceProvider` registered (the provider class does not exist yet — see note).

Note: `getPackageProviders()` references `MailogServiceProvider`, which is created in Task 3. **Run order:** create the provider stub from Task 3 Step 3 *before* running this step, OR temporarily return `[]` from `getPackageProviders()` and restore it in Task 3. Recommended: do Task 2 Steps 1–4, then Task 3 Steps 3–4 (create provider), then run `composer test` once here and at Task 3.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "test: Pest + Testbench harness with boot smoke test"
```

---

## Task 3: Service provider, config, singleton, and helper

**Files:**
- Create: `config/mailog.php`
- Create: `src/Mailog.php`
- Create: `src/Helpers.php`
- Create: `src/MailogServiceProvider.php`
- Create: `routes/web.php` (minimal; expanded in Task 6)
- Create: `resources/views/.gitkeep`
- Create: `components/.gitkeep` (replaced by a real component in Task 6)
- Test: `tests/Feature/ServiceProviderTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Feature/ServiceProviderTest.php`

```php
<?php

use Jiannius\Mailog\Mailog;

it('binds the mailog singleton and alias', function () {
    expect(app('mailog'))->toBeInstanceOf(Mailog::class);
    expect(app(Mailog::class))->toBe(app('mailog'));
});

it('exposes the mailog() helper returning the singleton', function () {
    expect(mailog())->toBeInstanceOf(Mailog::class);
    expect(mailog()->version())->toBeString();
});

it('merges the package config so config(mailog.*) is available', function () {
    expect(config('mailog.name'))->toBe('Mailog');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/ServiceProviderTest.php`
Expected: FAIL — class `Jiannius\Mailog\Mailog` not found / `mailog()` undefined.

- [ ] **Step 3: Create the implementation files**

`config/mailog.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Package settings
    |--------------------------------------------------------------------------
    |
    | Sample configuration. Replace these with your package's real options.
    | Host apps publish this file with:
    |   php artisan vendor:publish --tag=mailog-config
    |
    */

    'name' => 'Mailog',
];
```

`src/Mailog.php`:

```php
<?php

namespace Jiannius\Mailog;

class Mailog
{
    /**
     * The package version.
     */
    public function version(): string
    {
        return '0.1.0';
    }

    /**
     * Read a package config value (dot notation, scoped to "mailog").
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return config($key ? "mailog.{$key}" : 'mailog', $default);
    }
}
```

`src/Helpers.php`:

```php
<?php

use Jiannius\Mailog\Mailog;

if (! function_exists('mailog')) {
    /**
     * Resolve the Mailog singleton.
     */
    function mailog(): Mailog
    {
        return app('mailog');
    }
}
```

`src/MailogServiceProvider.php`:

```php
<?php

namespace Jiannius\Mailog;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Jiannius\Mailog\Commands\MailogCommand;

class MailogServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings and merge config.
     */
    public function register(): void
    {
        // Merge package config so config('mailog.*') is always available,
        // even before the host app publishes the file.
        $this->mergeConfigFrom(__DIR__.'/../config/mailog.php', 'mailog');

        // Bind the package singleton and expose it as app('mailog').
        $this->app->singleton(Mailog::class, fn (): Mailog => new Mailog());
        $this->app->alias(Mailog::class, 'mailog');
    }

    /**
     * Boot package resources into the host application.
     */
    public function boot(): void
    {
        // Routes — the host app can override by re-declaring the named route.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Migrations — picked up by the host app's `php artisan migrate`.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Views — referenced as view('mailog::...').
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailog');

        // Anonymous Blade components — usable as <x-mailog::name />.
        Blade::anonymousComponentPath(__DIR__.'/../components', 'mailog');

        // Translations — uncomment once a lang/ directory is added.
        // $this->loadTranslationsFrom(__DIR__.'/../lang', 'mailog');

        if ($this->app->runningInConsole()) {
            // Let the host app publish + override the config file.
            $this->publishes([
                __DIR__.'/../config/mailog.php' => config_path('mailog.php'),
            ], 'mailog-config');

            // Register the package's artisan commands.
            $this->commands([
                MailogCommand::class,
            ]);
        }
    }
}
```

Note: `MailogCommand` is created in Task 5. To keep the suite green between tasks, create the command stub now if running tasks strictly in order — or create `src/Commands/MailogCommand.php` from Task 5 Step 3 before running tests. The minimal stub:

```php
<?php

namespace Jiannius\Mailog\Commands;

use Illuminate\Console\Command;

class MailogCommand extends Command
{
    protected $signature = 'mailog:example {--force : Run without confirmation}';

    protected $description = 'An example package command — replace with your own.';

    public function handle(): int
    {
        $this->info('Mailog command ran. Edit '.static::class.' to implement it.');

        return self::SUCCESS;
    }
}
```

`routes/web.php` (minimal for now):

```php
<?php

use Illuminate\Support\Facades\Route;

// Example package route. Rename or remove for your package.
Route::get('/mailog', fn () => response()->json([
    'package' => 'mailog',
    'version' => mailog()->version(),
]))->name('mailog.index');
```

Create empty keep-files:
- `resources/views/.gitkeep` (empty)
- `components/.gitkeep` (empty)

- [ ] **Step 4: Regenerate autoload (picks up `src/Helpers.php` + new classes)**

Run: `composer dump-autoload`
Expected: `Generated optimized autoload files`

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/ServiceProviderTest.php`
Expected: PASS — 3 passed.

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: PASS — all tests green (SmokeTest + ServiceProviderTest).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: service provider, config, singleton entry-point, and helper"
```

---

## Task 4: Enum trait

**Files:**
- Create: `src/Traits/Enum.php`
- Test: `tests/Unit/EnumTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Unit/EnumTest.php`

```php
<?php

use Jiannius\Mailog\Traits\Enum;

enum MailogStatus: string
{
    use Enum;

    case ACTIVE = 'active';
    case PENDING = 'pending';
    case TRASHED = 'trashed';
}

it('lists cases, excluding TRASHED by default', function () {
    expect(MailogStatus::all()->pluck('value')->all())->toBe(['active', 'pending']);
    expect(MailogStatus::all(false))->toHaveCount(3);
});

it('builds an option array and a humanized label', function () {
    expect(MailogStatus::ACTIVE->option())->toBe(['value' => 'active', 'label' => 'Active']);
    expect(MailogStatus::PENDING->label())->toBe('Pending');
});

it('resolves a case from a name or value with get()', function () {
    expect(MailogStatus::get('active'))->toBe(MailogStatus::ACTIVE);
    expect(MailogStatus::get('ACTIVE'))->toBe(MailogStatus::ACTIVE);
    expect(MailogStatus::get(MailogStatus::PENDING))->toBe(MailogStatus::PENDING);
});

it('matches with is()/isNot()', function () {
    expect(MailogStatus::ACTIVE->is('active'))->toBeTrue();
    expect(MailogStatus::ACTIVE->is('active', 'pending'))->toBeTrue();
    expect(MailogStatus::ACTIVE->isNot('pending'))->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/EnumTest.php`
Expected: FAIL — trait `Jiannius\Mailog\Traits\Enum` not found.

- [ ] **Step 3: Create `src/Traits/Enum.php`** (generalized from `jiannius/atom`)

```php
<?php

namespace Jiannius\Mailog\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

trait Enum
{
    /**
     * Resolve a case from its name or value (case-insensitive; passes through real cases).
     */
    public static function get($name): mixed
    {
        if (! is_string($name)) {
            return $name;
        }

        if ($value = static::tryFrom($name)) {
            return $value;
        }

        $name = str($name)->upper()->replace('-', '_')->replace(' ', '_')->toString();

        return static::all(false)->first(fn ($case): bool => $case->is($name));
    }

    /**
     * All cases as a collection (excludes TRASHED unless $filtered is false).
     */
    public static function all(bool $filtered = true): Collection
    {
        $cases = collect(static::cases());

        return $filtered
            ? $cases->filter(fn ($case): bool => $case->isNot('TRASHED'))->values()
            : $cases;
    }

    /**
     * The case as a select option array.
     *
     * @return array{value: string, label: string}
     */
    public function option(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * The case as an array including a humanized label.
     *
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * A humanized label derived from the case value.
     */
    public function label(): string
    {
        return str()->headline($this->value);
    }

    /**
     * Whether this case matches any of the given names or values.
     */
    public function is(): bool
    {
        $val = func_num_args() > 1 ? func_get_args() : (array) func_get_arg(0);

        return in_array($this->value, $val, true) || in_array($this->name, $val, true);
    }

    /**
     * Whether this case matches none of the given names or values.
     */
    public function isNot(...$val): bool
    {
        return ! $this->is(...$val);
    }

    /**
     * The case name (or value) as a Stringable.
     */
    public function str(string $type = 'name'): Stringable
    {
        return new Stringable($this->{$type});
    }

    /**
     * The case name (or value) in snake_case.
     */
    public function snake(string $type = 'name'): string
    {
        return (string) str($this->{$type})->lower()->snake();
    }

    /**
     * The case name (or value) as a URL slug.
     */
    public function slug(string $type = 'name'): string
    {
        return (string) str($this->{$type})->lower()->slug();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/EnumTest.php`
Expected: PASS — 4 passed.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: FULL_UPPERCASE backed-enum trait"
```

---

## Task 5: Example model, migration, factory, and command

**Files:**
- Create: `src/Models/Mailog.php`
- Create: `database/migrations/0001_01_01_000000_create_mailogs_table.php`
- Create: `database/factories/MailogFactory.php`
- Create: `src/Commands/MailogCommand.php` (if not already created in Task 3)
- Test: `tests/Feature/MailogModelTest.php`
- Test: `tests/Feature/CommandTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/MailogModelTest.php`:

```php
<?php

use Jiannius\Mailog\Models\Mailog;

it('creates a mailog with a ULID id and json data', function () {
    $mailog = Mailog::factory()->create([
        'name' => 'Example',
        'data' => ['key' => 'value'],
    ]);

    expect($mailog->id)->toBeString()->toHaveLength(26);
    expect($mailog->name)->toBe('Example');
    expect($mailog->data)->toBe(['key' => 'value']);
    expect(Mailog::count())->toBe(1);
});
```

`tests/Feature/CommandTest.php`:

```php
<?php

it('runs the example artisan command', function () {
    $this->artisan('mailog:example')
        ->assertExitCode(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/MailogModelTest.php tests/Feature/CommandTest.php`
Expected: FAIL — `Jiannius\Mailog\Models\Mailog` not found; command `mailog:example` not defined (if the command stub was not created in Task 3).

- [ ] **Step 3: Create the implementation files**

`src/Models/Mailog.php`:

```php
<?php

namespace Jiannius\Mailog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jiannius\Mailog\Database\Factories\MailogFactory;

class Mailog extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Mass-assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'data',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * Resolve the model's factory.
     */
    protected static function newFactory(): MailogFactory
    {
        return MailogFactory::new();
    }
}
```

`database/migrations/0001_01_01_000000_create_mailogs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('mailogs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailogs');
    }
};
```

`database/factories/MailogFactory.php`:

```php
<?php

namespace Jiannius\Mailog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jiannius\Mailog\Models\Mailog;

class MailogFactory extends Factory
{
    /**
     * The model the factory builds.
     *
     * @var class-string<Mailog>
     */
    protected $model = Mailog::class;

    /**
     * Default attribute state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'data' => [],
        ];
    }
}
```

`src/Commands/MailogCommand.php` (create if not already present from Task 3):

```php
<?php

namespace Jiannius\Mailog\Commands;

use Illuminate\Console\Command;

class MailogCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'mailog:example {--force : Run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'An example package command — replace with your own.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Mailog command ran. Edit '.static::class.' to implement it.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Regenerate autoload (new `Database\Factories` namespace)**

Run: `composer dump-autoload`
Expected: `Generated optimized autoload files`

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/MailogModelTest.php tests/Feature/CommandTest.php`
Expected: PASS — 2 passed. (`RefreshDatabase` runs the package migration into the in-memory sqlite, so the `mailogs` table exists.)

- [ ] **Step 6: Run the full suite**

Run: `composer test`
Expected: PASS — all green.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: example ULID model, migration, factory, and artisan command"
```

---

## Task 6: Example route and anonymous Blade component

**Files:**
- Modify: `routes/web.php` (already created in Task 3 — verify content)
- Create: `components/example.blade.php` (delete `components/.gitkeep`)
- Test: `tests/Feature/RouteTest.php`
- Test: `tests/Feature/ComponentTest.php`

- [ ] **Step 1: Write the failing tests**

`tests/Feature/RouteTest.php`:

```php
<?php

it('responds on the example package route', function () {
    $this->get('/mailog')
        ->assertOk()
        ->assertJson(['package' => 'mailog'])
        ->assertJsonStructure(['package', 'version']);
});
```

`tests/Feature/ComponentTest.php`:

```php
<?php

use Illuminate\Support\Facades\Blade;

it('renders the anonymous blade component under the mailog namespace', function () {
    $html = Blade::render('<x-mailog::example title="Hello" >body</x-mailog::example>');

    expect($html)->toContain('Hello')->toContain('body');
});
```

- [ ] **Step 2: Run the tests to verify failure state**

Run: `vendor/bin/pest tests/Feature/RouteTest.php tests/Feature/ComponentTest.php`
Expected: RouteTest passes (route created in Task 3); ComponentTest FAILS — component `mailog::example` not found.

- [ ] **Step 3: Create `components/example.blade.php`** and remove the keep-file

```blade
{{-- Anonymous Blade component. Use as <x-mailog::example title="..." />. --}}
@props(['title' => 'Mailog'])

<div {{ $attributes->merge(['class' => 'mailog-example']) }}>
    <h2>{{ $title }}</h2>
    {{ $slot }}
</div>
```

Run: `git rm -f --quiet components/.gitkeep 2>/dev/null; rm -f components/.gitkeep`

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/RouteTest.php tests/Feature/ComponentTest.php`
Expected: PASS — 2 passed.

- [ ] **Step 5: Run the full suite**

Run: `composer test`
Expected: PASS — all green (this is the full green-suite gate for the example surface).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: example route and anonymous blade component"
```

---

## Task 7: Laravel Boost runtime wiring

**Files:**
- Create: `.mcp.json`
- Create: `.cursor/mcp.json`
- Create: `boost.json`
- Create: `.ai/guidelines/mailog.blade.php`

- [ ] **Step 1: Create `.mcp.json`** (Boost MCP server via Testbench-as-artisan)

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "vendor/bin/testbench",
            "args": [
                "boost:mcp"
            ]
        }
    }
}
```

- [ ] **Step 2: Create `.cursor/mcp.json`** (same wiring for Cursor)

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "vendor/bin/testbench",
            "args": [
                "boost:mcp"
            ]
        }
    }
}
```

- [ ] **Step 3: Create `boost.json`**

```json
{
    "agents": [
        "claude_code"
    ],
    "cloud": false,
    "guidelines": true,
    "mcp": true,
    "nightwatch": false,
    "packages": [],
    "sail": false
}
```

- [ ] **Step 4: Create `.ai/guidelines/mailog.blade.php`** (jiannius guidelines Boost merges into `CLAUDE.md` on `boost:install`)

```blade
## Package development (jiannius/mailog)

This is a Laravel **package** (composer `type: library`, PSR-4 `Jiannius\Mailog\`), not an app. There is no `artisan` binary — dev/test/Boost tooling runs through Orchestra Testbench's `vendor/bin/testbench` against a throwaway Laravel app configured by `testbench.yaml`.

- Create classes the Laravel way, but there is no app `make:` here — add files under `src/` following the existing structure and namespaces.
- Every change must be covered by a Pest test in `tests/Feature` or `tests/Unit` (extends `Tests\TestCase`, in-memory sqlite). Run `composer test`.
- Models on main tables use ULID primary keys (`HasUlids` + `$table->ulid('id')->primary()`) and a `data` json column for metadata.
- Backed enums mix in `Jiannius\Mailog\Traits\Enum` with `FULL_UPPERCASE` cases.
- The package's public API hangs off the `Mailog` singleton (`app('mailog')` / the `mailog()` helper).

### Working guidelines

**Think before coding.** State assumptions; if multiple interpretations exist, surface them rather than picking silently. If something is unclear, stop and ask.

**Simplicity first.** Minimum code that solves the problem. No speculative features, abstractions for single-use code, or error handling for impossible scenarios.

**Surgical changes.** Touch only what the task requires. Match existing style. Don't refactor unrelated code; remove only orphans your own change created.

**Goal-driven execution.** Turn the task into a verifiable goal ("write a failing test, then make it pass") and loop until verified.
```

- [ ] **Step 5: Verify the Boost MCP server boots through Testbench**

Run: `vendor/bin/testbench boost:mcp --help`
Expected: Help output for the `boost:mcp` command (description "Starts Laravel Boost...") — proves the command is registered in the Testbench-booted app.

Then verify the server actually starts and is killable (it is a long-running stdio server, so start it and stop after a moment):

Run: `( vendor/bin/testbench boost:mcp & echo $! > /tmp/boost_mcp.pid; sleep 4; kill "$(cat /tmp/boost_mcp.pid)" 2>/dev/null ) ; echo "boost:mcp exit handled"`
Expected: The process starts without a fatal error (no stack trace), waits, then is terminated. "boost:mcp exit handled" prints. If it errors immediately with a stack trace, capture the error and treat it as a blocker to resolve before continuing.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: Laravel Boost runtime wiring (mcp via testbench, boost.json, .ai guidelines)"
```

---

## Task 8: Consumer-facing Boost guidelines

**Files:**
- Create: `resources/boost/guidelines/core.blade.php`
- Test: `tests/Feature/ConsumerGuidelinesTest.php`

This file is what Boost merges into the `CLAUDE.md` of any host app that `composer require`s the (renamed) package and lists it in its own `boost.json` `packages`. Write it as usage guidance for someone building *with* the package.

- [ ] **Step 1: Write the failing test** — `tests/Feature/ConsumerGuidelinesTest.php` (asserts the file exists and renders as valid Blade)

```php
<?php

use Illuminate\Support\Facades\Blade;

it('ships a renderable consumer-facing boost guideline', function () {
    $path = __DIR__.'/../../resources/boost/guidelines/core.blade.php';

    expect(file_exists($path))->toBeTrue();

    $rendered = Blade::render(file_get_contents($path));

    expect($rendered)->toContain('Mailog');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/ConsumerGuidelinesTest.php`
Expected: FAIL — file does not exist.

- [ ] **Step 3: Create `resources/boost/guidelines/core.blade.php`**

```blade
## Mailog

`jiannius/mailog` is a Laravel package. Its service provider auto-registers and loads routes, migrations, views (`mailog::*`), and anonymous Blade components (`<x-mailog::*>`) into this app.

### Public API — the `mailog()` helper

The package exposes a single entry-point resolvable via the `mailog()` helper or `app('mailog')`:

@verbatim
<code-snippet name="Using the mailog singleton" lang="php">
mailog()->version();              // package version
mailog()->config('name');         // read config('mailog.name')
</code-snippet>
@endverbatim

### Config

Publish and override the package config:

@verbatim
<code-snippet name="Publish config" lang="bash">
php artisan vendor:publish --tag=mailog-config
</code-snippet>
@endverbatim

Values live under `config('mailog.*')`.

### Enums

Backed enums in this app may mix in `Jiannius\Mailog\Traits\Enum`; cases are `FULL_UPPERCASE`. The trait provides `all()`, `option()`, `label()`, `get()`, and `is()`/`isNot()`.

@verbatim
<code-snippet name="Mailog-backed enum" lang="php">
<?php
namespace App\Enums;

use Jiannius\Mailog\Traits\Enum;

enum Status: string
{
    use Enum;

    case ACTIVE = 'active';
    case PENDING = 'pending';
}

Status::all()->map->option()->all();   // select options
</code-snippet>
@endverbatim

### Components

Use `<x-mailog::example title="..." />` for the package's anonymous Blade components. Check `vendor/jiannius/mailog/components/` for the full set before writing custom markup.
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/ConsumerGuidelinesTest.php`
Expected: PASS — 1 passed (the `@verbatim` blocks ensure the `<code-snippet>` examples are not Blade-compiled, and "Mailog" appears in the output).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: consumer-facing Boost guidelines (resources/boost/guidelines)"
```

---

## Task 9: Package CLAUDE.md

**Files:**
- Create: `CLAUDE.md`

Hand-authored (deterministic). It documents working *on* the package and the Testbench+Boost workflow. `vendor/bin/testbench boost:install` can regenerate the Boost guideline block from `.ai/guidelines/` — noted in the file.

- [ ] **Step 1: Create `CLAUDE.md`**

````markdown
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jiannius/mailog` is a **Laravel package mailog** — a clone-and-rename starting point for jiannius Laravel packages (composer `type: library`, PSR-4 `Jiannius\Mailog\`). It is consumed by host Laravel apps via `composer require`; this repo is the library itself, not a host app.

Because a package has no `artisan` binary, all dev/test/AI tooling runs through **Orchestra Testbench**: `vendor/bin/testbench` is the artisan equivalent, booting a throwaway Laravel 13 app (configured by `testbench.yaml`) with `MailogServiceProvider` registered.

To turn this mailog into a real package, run `php configure.php` (see README) — it rewrites the `Mailog`/`mailog` placeholder identity and self-deletes.

## Common commands

```bash
composer install                                   # install dependencies
composer test                                      # Pest 4 + Testbench suite
vendor/bin/pest tests/Feature/RouteTest.php        # single test file
vendor/bin/pest --filter='binds the mailog singleton'  # single test
composer lint                                      # vendor/bin/pint
vendor/bin/testbench boost:mcp                     # start the Boost MCP server (used by editors)
vendor/bin/testbench boost:install                 # (re)generate the Boost guidelines block
vendor/bin/testbench serve                         # boot the workbench app for manual checks
```

## Laravel Boost

Laravel Boost is installed (dev) and runs through Testbench. Editors connect via `.mcp.json` / `.cursor/mcp.json`, which invoke `vendor/bin/testbench boost:mcp`.

- Use Boost's `search-docs` tool before code changes — it returns version-specific docs for the installed packages.
- The Boost guidelines block is generated by `vendor/bin/testbench boost:install` from the core Boost guidelines plus this repo's `.ai/guidelines/`. Re-run it after changing dependencies or `.ai/guidelines/`.

## Two guideline surfaces

1. **This `CLAUDE.md`** guides work *on the package itself*.
2. **`resources/boost/guidelines/core.blade.php`** ships *to consuming apps*: when a host app installs this package and lists it in its own `boost.json` `packages`, Boost merges that file into the host's `CLAUDE.md`. Keep it as usage guidance for someone building *with* the package.

## Architecture

### Service-provider wiring (`src/MailogServiceProvider.php`)

`register()` merges `config/mailog.php` and binds the `Mailog` singleton (aliased `app('mailog')`). `boot()` loads `routes/web.php`, `database/migrations/`, `resources/views/` (view namespace `mailog`), and the anonymous Blade components in `components/` (`<x-mailog::name>`). Console-only: publishes the config (tag `mailog-config`) and registers `mailog:example`. Read this file first when something seems to come from nowhere.

### Singleton entry-point (`src/Mailog.php` → `app('mailog')` / `mailog()`)

The package's public API object, resolvable via the container alias `mailog` or the autoloaded `mailog()` helper (`src/Helpers.php`). Add cross-cutting package methods here.

### Conventions

- **Enums** mix in `Jiannius\Mailog\Traits\Enum`; cases are `FULL_UPPERCASE` backed values. The trait provides `all()`, `option()`, `label()`, `get()`, `is()`/`isNot()`.
- **Models** on main tables use ULID primary keys (`HasUlids` + `$table->ulid('id')->primary()`) plus a `data` json column for metadata.
- **PHP style**: curly braces on all control structures; explicit return types and parameter type hints; one-line PHPDoc on every public/private method; PHP 8 constructor property promotion.
- **Tests**: every feature has a Pest test extending `Tests\TestCase` (Orchestra Testbench, in-memory sqlite). Run the minimum tests needed (`vendor/bin/pest --filter=...`).

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
````

- [ ] **Step 2: Verify the file is well-formed and present**

Run: `head -5 CLAUDE.md && echo '---' && grep -c '##' CLAUDE.md`
Expected: The title prints; `grep -c` returns a non-zero count of section headers.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "docs: package CLAUDE.md (architecture + Boost workflow + working guidelines)"
```

---

## Task 10: `configure.php` rename script

**Files:**
- Create: `configure.php`

Pure-PHP interactive script (no dependencies). Prompts for identity, find-replaces placeholder tokens across the tree, renames files, prints next steps, and deletes itself. Verified by running it against a throwaway copy with piped answers.

- [ ] **Step 1: Create `configure.php`**

```php
<?php

/**
 * Interactive configurator for the jiannius package mailog.
 *
 * Usage: php configure.php
 *
 * Rewrites the "Mailog"/"mailog" placeholder identity to your package,
 * renames placeholder files, then deletes itself.
 */

function ask(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? " ({$default})" : '';
    echo "\e[1m{$question}\e[0m{$suffix}: ";
    $answer = trim((string) fgets(STDIN));

    return $answer !== '' ? $answer : $default;
}

function studly(string $value): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
}

function slug(string $value): string
{
    $value = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value));

    return trim($value, '-');
}

$root = __DIR__;

// ---- Gather identity ------------------------------------------------------
$vendor = slug(ask('Vendor', 'jiannius'));
$packageSlug = slug(ask('Package name (slug)', 'mailog'));
$studly = studly($packageSlug);
$studly = studly(ask('StudlyCase name (namespace + classes)', $studly));
$authorName = ask('Author name', 'TJ');
$authorEmail = ask('Author email', 'tj@jiannius.com');
$description = ask('Description', "Jiannius {$studly}");

$vendorStudly = studly($vendor);

// Token => replacement. Order matters: do specific/compound tokens first.
$replacements = [
    'jiannius/mailog'   => "{$vendor}/{$packageSlug}",
    'Jiannius\\Mailog'  => "{$vendorStudly}\\{$studly}",
    'Jiannius\\\\Mailog' => "{$vendorStudly}\\\\{$studly}",   // escaped form in composer.json
    'Mailog'            => $studly,
    'mailog'            => $packageSlug,
];

$authorReplacements = [
    'TJ'               => $authorName,
    'tj@jiannius.com'  => $authorEmail,
    'Jiannius Laravel package mailog' => $description,
];

// ---- Walk the tree --------------------------------------------------------
$skipDirs = ['.git', 'vendor', 'node_modules', 'build', '.phpunit.cache'];

$rii = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $current) use ($skipDirs, $root): bool {
            $rel = ltrim(str_replace($root, '', $current->getPathname()), DIRECTORY_SEPARATOR);
            $top = explode(DIRECTORY_SEPARATOR, $rel)[0];

            return ! in_array($top, $skipDirs, true);
        }
    )
);

$files = [];
foreach ($rii as $file) {
    if ($file->isFile()) {
        $files[] = $file->getPathname();
    }
}

// ---- Replace contents -----------------------------------------------------
foreach ($files as $path) {
    if ($path === __FILE__) {
        continue; // don't rewrite the configurator itself
    }

    $contents = file_get_contents($path);
    $original = $contents;

    foreach ($replacements as $from => $to) {
        $contents = str_replace($from, $to, $contents);
    }
    foreach ($authorReplacements as $from => $to) {
        $contents = str_replace($from, $to, $contents);
    }

    if ($contents !== $original) {
        file_put_contents($path, $contents);
        echo "updated  ".ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR)."\n";
    }
}

// ---- Rename files containing the placeholder name -------------------------
foreach ($files as $path) {
    $base = basename($path);
    if (str_contains($base, 'Mailog')) {
        $newBase = str_replace('Mailog', $studly, $base);
        $newPath = dirname($path).DIRECTORY_SEPARATOR.$newBase;
        rename($path, $newPath);
        echo "renamed  {$base} -> {$newBase}\n";
    }
}

// ---- Done -----------------------------------------------------------------
echo "\n\e[32mDone.\e[0m Package configured as {$vendor}/{$packageSlug} ({$vendorStudly}\\{$studly}).\n\n";
echo "Next steps:\n";
echo "  composer install\n";
echo "  vendor/bin/testbench boost:install   # regenerate the CLAUDE.md Boost block\n";
echo "  composer test\n\n";

@unlink(__FILE__);
echo "configure.php removed itself.\n";
```

- [ ] **Step 2: Verify `configure.php` parses**

Run: `php -l configure.php`
Expected: `No syntax errors detected in configure.php`

- [ ] **Step 3: Test the rename end-to-end on a throwaway copy (do not run it on the mailog itself)**

```bash
TMP="$(mktemp -d)/banner"
rsync -a --exclude='.git' --exclude='vendor' --exclude='node_modules' /Users/tj/Projects/jiannius/mailog-package/ "$TMP/"
cd "$TMP"
printf 'jiannius\nbanner\nBanner\nTJ\ntj@jiannius.com\nJiannius Banner\n' | php configure.php
echo "===== verify rename ====="
grep -q '"name": "jiannius/banner"' composer.json && echo "OK composer name"
grep -q 'Jiannius\\\\Banner' composer.json && echo "OK namespace"
test -f src/BannerServiceProvider.php && echo "OK provider renamed"
test -f src/Banner.php && echo "OK singleton renamed"
test ! -f configure.php && echo "OK configure.php self-deleted"
grep -rq 'Mailog' src/ && echo "WARN: leftover Mailog in src/" || echo "OK no leftover Mailog in src/"
cd /Users/tj/Projects/jiannius/mailog-package
rm -rf "$(dirname "$TMP")"
```

Expected: prints `OK composer name`, `OK namespace`, `OK provider renamed`, `OK singleton renamed`, `OK configure.php self-deleted`, `OK no leftover Mailog in src/`. If "WARN: leftover Mailog" prints, inspect which file/token was missed and extend `$replacements`/rename logic, then re-test.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: configure.php interactive rename script"
```

---

## Task 11: Editor settings, CI workflows, README, and final verification

**Files:**
- Create: `.claude/settings.local.json`
- Create: `.github/workflows/tests.yml`
- Create: `.github/workflows/lint.yml`
- Modify: `README.md` (final content)

- [ ] **Step 1: Create `.claude/settings.local.json`**

```json
{
    "permissions": {
        "allow": [
            "Bash(composer:*)",
            "Bash(vendor/bin/pest:*)",
            "Bash(vendor/bin/pint:*)",
            "Bash(vendor/bin/testbench:*)"
        ]
    }
}
```

- [ ] **Step 2: Create `.github/workflows/tests.yml`**

```yaml
name: tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Install dependencies
        run: composer update --prefer-dist --no-interaction --no-progress

      - name: Run tests
        run: composer test
```

- [ ] **Step 3: Create `.github/workflows/lint.yml`**

```yaml
name: lint

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Install dependencies
        run: composer update --prefer-dist --no-interaction --no-progress

      - name: Check code style
        run: vendor/bin/pint --test
```

- [ ] **Step 4: Write the final `README.md`**

````markdown
# Jiannius Mailog

A clone-and-rename starting point for jiannius Laravel **packages**. Ships with Laravel Boost (via Orchestra Testbench), a Pest 4 test suite, dual AI-guideline surfaces, and a `configure.php` rename script.

This is the package-world counterpart to `jiannius/mailog-project` (the Laravel app mailog).

## Quick start

```bash
git clone <repo-url> my-package
cd my-package
php configure.php                      # rename Mailog -> your package; self-deletes
composer install
vendor/bin/testbench boost:install     # (re)generate the CLAUDE.md Boost block
composer test                          # Pest + Testbench, green out of the box
```

## Why Testbench

A package has no `artisan` binary. Orchestra Testbench's `vendor/bin/testbench` boots a
throwaway Laravel app (configured by `testbench.yaml`) with the package's service provider
registered — so tests, artisan commands, and Laravel Boost all run inside a real app context.

```bash
composer test                          # run the suite
composer lint                          # vendor/bin/pint
vendor/bin/testbench boost:mcp         # start the Boost MCP server (editors use this)
vendor/bin/testbench serve             # boot the app for manual checks
```

## AI guidelines — two surfaces

- **`CLAUDE.md`** — guidance for working *on this package*. The Boost guideline block is
  generated by `vendor/bin/testbench boost:install` from the core Boost guidelines plus
  `.ai/guidelines/`.
- **`resources/boost/guidelines/core.blade.php`** — guidance shipped *to consuming apps*.
  When a host app installs this package and lists it in its `boost.json` `packages`, Boost
  merges this file into the host app's `CLAUDE.md`.

## What's inside

| Path | Purpose |
| --- | --- |
| `src/MailogServiceProvider.php` | Wires routes, migrations, views, components, command, config |
| `src/Mailog.php` | Singleton entry-point — `app('mailog')` / `mailog()` |
| `src/Helpers.php` | Autoloaded `mailog()` helper |
| `src/Traits/Enum.php` | `FULL_UPPERCASE` backed-enum trait |
| `src/Models/Mailog.php` | ULID example model with a `data` json column |
| `src/Commands/MailogCommand.php` | Example artisan command (`mailog:example`) |
| `config/mailog.php` | Publishable config (tag `mailog-config`) |
| `routes/web.php` | Example route |
| `components/example.blade.php` | Anonymous Blade component (`<x-mailog::example>`) |
| `tests/` | Pest 4 + Testbench suite |
| `configure.php` | One-shot rename script (deletes itself) |

## License

MIT.
````

- [ ] **Step 5: Apply code style with Pint**

Run: `composer lint`
Expected: Pint runs and reports files inspected; any auto-fixes are applied. Re-run `composer test` after to confirm still green.

- [ ] **Step 6: Final full verification**

Run each and confirm:

```bash
composer test
```
Expected: PASS — all suites green (SmokeTest, ServiceProviderTest, EnumTest, MailogModelTest, CommandTest, RouteTest, ComponentTest, ConsumerGuidelinesTest).

```bash
vendor/bin/testbench boost:mcp --help
```
Expected: `boost:mcp` help output (Boost is live).

```bash
composer validate --no-check-publish && git status --porcelain
```
Expected: `./composer.json is valid`; a clean or intentionally-staged working tree.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: editor settings, CI workflows, README, and final polish"
```

---

## Self-review (completed during planning)

**Spec coverage:**
- Identity & placeholders → Task 1 + Task 10. ✓
- Directory layout (lean generalization) → Tasks 1–8. ✓
- composer.json (library, deps, autoload, scripts) → Task 1. ✓
- ServiceProvider full boot() wiring → Task 3. ✓
- Mailog singleton + Helpers → Task 3. ✓
- Enum trait → Task 4. ✓
- Example model + migration + factory → Task 5. ✓
- Routes + anonymous component → Task 3 (route) + Task 6. ✓
- Two guideline surfaces: CLAUDE.md (Task 9) + `resources/boost/guidelines/core.blade.php` (Task 8). ✓
- Boost/Testbench mechanics (testbench.yaml, .mcp.json, .cursor/mcp.json, boost.json, .ai/guidelines) → Task 1 (testbench.yaml) + Task 7. ✓
- Pest 4 + Testbench tests → Tasks 2–8. ✓
- configure.php → Task 10. ✓
- Dotfiles (.gitignore, .gitattributes, .editorconfig, LICENSE, CHANGELOG), CI, .claude → Tasks 1 + 11. ✓

**Resolved caveat:** the spec's "if composer/network unavailable" caveat is moot — environment confirmed php 8.4 + composer 2.9.5 + packagist reachable, so all verification runs for real. CLAUDE.md is hand-authored (deterministic) with `boost:install` documented as the regeneration path; `boost:mcp` booting is the live-Boost proof.

**Deviation from spec:** spec listed a `workbench/` directory; the plan uses `testbench.yaml` (provider registration) which is sufficient for Boost + tests, and reserves the `Workbench\` autoload-dev namespaces in `composer.json` for when custom workbench app code is actually needed (YAGNI now). No empty `workbench/` dir is committed.

**Type/name consistency:** `app('mailog')` alias, `mailog()` helper, `Mailog` singleton, `Jiannius\Mailog\Traits\Enum`, `mailog::` view/component namespace, `mailog-config` publish tag, `mailog:example` command, `mailogs` table — used consistently across Tasks 3–9.

**Cross-task ordering note:** `MailogServiceProvider` (Task 3) and `MailogCommand` (Task 5) are referenced by earlier-running code (`tests/TestCase.php` in Task 2; the provider's `commands()` in Task 3). The plan calls this out at Task 2 Step 5 and Task 3 Step 3 with the stub to create first, so the suite stays green between tasks.
````
