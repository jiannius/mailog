<?php

namespace Jiannius\Mailog;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Mail\Events\MessageSending;

class Mailog
{
    /**
     * Header a host sets on a message to opt it out of logging.
     */
    public const SKIP_HEADER = 'X-Mailog-Skip';

    /**
     * Host-set resolver for the user to attribute a log to.
     */
    protected static ?Closure $userResolver = null;

    /**
     * Host-set resolver for extra columns to fill on a log.
     */
    protected static ?Closure $dataResolver = null;

    /**
     * Set (or, with null, reset) the user resolver. Called by the host app in
     * a service provider — cache-safe, unlike a closure in the config file.
     */
    public static function resolveUserUsing(?Closure $resolver): void
    {
        static::$userResolver = $resolver;
    }

    /**
     * Set (or, with null, reset) the data resolver for host custom columns.
     */
    public static function resolveDataUsing(?Closure $resolver): void
    {
        static::$dataResolver = $resolver;
    }

    /**
     * Resolve the user attributes to store on a sending message's log.
     *
     * @return array{user_id?: mixed, user_name?: ?string}
     */
    public function resolveUser(MessageSending $event): array
    {
        $resolver = static::$userResolver ?? fn (): mixed => auth()->user();

        $user = $resolver($event);

        if ($user === null) {
            return [];
        }

        if (is_array($user)) {
            return [
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
            ];
        }

        return [
            'user_id' => $user->getKey(),
            'user_name' => $user->name ?? null,
        ];
    }

    /**
     * Resolve host-supplied custom attributes for a sending message's log.
     *
     * @return array<string, mixed>
     */
    public function resolveData(MessageSending $event): array
    {
        $resolver = static::$dataResolver ?? fn (): array => [];

        // Treat an empty/null/false resolver return as "no extra data".
        return $resolver($event) ?: [];
    }

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
