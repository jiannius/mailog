<?php

namespace Jiannius\Mailog;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Jiannius\Mailog\Listeners\MailLogListener;

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
        $this->app->singleton(Mailog::class, fn (): Mailog => new Mailog);
        $this->app->alias(Mailog::class, 'mailog');

        // Singleton so one instance bridges MessageSending → MessageSent via its map.
        $this->app->singleton(MailLogListener::class);
    }

    /**
     * Boot package resources into the host application.
     *
     * Each hook below is commented out until the matching directory/file
     * exists — see the README "How-to recipes" for what to create.
     */
    public function boot(): void
    {
        // Migrations — host apps pick them up with `php artisan migrate`.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Capture every outgoing email (logging is on by default).
        Event::listen(MessageSending::class, [MailLogListener::class, 'sending']);
        Event::listen(MessageSent::class, [MailLogListener::class, 'sent']);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mailog.php' => config_path('mailog.php'),
            ], 'mailog-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'mailog-migrations');
        }
    }
}
