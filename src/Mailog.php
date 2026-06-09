<?php

namespace Jiannius\Mailog;

use Composer\InstalledVersions;

class Mailog
{
    /**
     * The package version.
     */
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('jiannius/mailog') ?? 'dev';
    }

    /**
     * Read a package config value (dot notation, scoped to "mailog").
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return config($key ? "mailog.{$key}" : 'mailog', $default);
    }
}
