<?php

namespace Jiannius\Mailog;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
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

        // Capture every outgoing email (logging is on by default). Registered here, not in
        // boot(), so we run ahead of every listener a host registers during boot, in
        // EventServiceProvider or by discovery: MessageSending is dispatched with until(),
        // which stops at the first non-null return and would otherwise skip us. The row
        // itself is inserted by MailLogTransport just before the send, so a mail a host
        // listener cancels never gets one.
        $this->app['events']->listen(MessageSending::class, [MailLogListener::class, 'sending']);
        $this->app['events']->listen(MessageSent::class, [MailLogListener::class, 'sent']);
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
