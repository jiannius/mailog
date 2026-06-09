<?php

namespace Jiannius\Mailog;

use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Boot package resources into the host application.
     *
     * Each hook below is commented out until the matching directory/file
     * exists — see the README "How-to recipes" for what to create.
     */
    public function boot(): void
    {
        // Routes — uncomment once routes/web.php exists.
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Migrations — uncomment once database/migrations/ exists; host apps
        // pick them up with plain `php artisan migrate`.
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Views — uncomment once resources/views/ exists; view('mailog::...').
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailog');

        // Anonymous Blade components — uncomment once components/ exists and
        // import Illuminate\Support\Facades\Blade; usable as <x-mailog::name />.
        // Blade::anonymousComponentPath(__DIR__.'/../components', 'mailog');

        // Translations — uncomment once a lang/ directory is added.
        // $this->loadTranslationsFrom(__DIR__.'/../lang', 'mailog');

        if ($this->app->runningInConsole()) {
            // Let the host app publish + override the config file.
            $this->publishes([
                __DIR__.'/../config/mailog.php' => config_path('mailog.php'),
            ], 'mailog-config');

            // Artisan commands — uncomment and list the command classes once added.
            // $this->commands([
            //     Commands\ExampleCommand::class,
            // ]);
        }
    }
}
