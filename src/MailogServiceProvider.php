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

        // Singleton so one instance holds the message-keyed maps between sending and the outcome.
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

        // Capture every outgoing email (logging is on by default). The row itself is
        // inserted by MailLogTransport just before the send, so a mail a host listener
        // cancels (MessageSending is dispatched with until()) never gets one.
        Event::listen(MessageSending::class, [MailLogListener::class, 'sending']);
        Event::listen(MessageSent::class, [MailLogListener::class, 'sent']);

        // Wrap the configured mailers' transports now (sending() re-wraps lazily, e.g. after a purge).
        if (config('mailog.enabled', true)) {
            $this->app->make(MailLogListener::class)->wrapConfiguredMailers();
        }

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
