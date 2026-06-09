## Mailog

`jiannius/mailog` is a Laravel package. Its service provider auto-registers, merges its config, and exposes a singleton entry-point into this app.

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
