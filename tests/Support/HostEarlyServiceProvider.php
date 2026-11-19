<?php

namespace Jiannius\Mailog\Tests\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\ServiceProvider;

/**
 * Stands in for a host app listener that runs before the package's: while
 * config('mailog_test.early') is true it returns a non-null value (the result
 * of adding a header), which makes Laravel's until() stop the MessageSending
 * chain before the package's listener runs. Listed before the package provider
 * in TestCase and registered in register(), so it registers first.
 */
class HostEarlyServiceProvider extends ServiceProvider
{
    /**
     * Register the chain-stopping listener in register(), listed before the
     * package provider so it is registered ahead of the package's listener.
     */
    public function register(): void
    {
        $this->app['events']->listen(MessageSending::class, function (MessageSending $event) {
            if (config('mailog_test.early')) {
                return $event->message->getHeaders()->addTextHeader('X-Host-Early', '1');
            }
        });
    }

    /**
     * Register a second chain-stopping listener in boot(); this provider boots
     * before the package's, so it would run first if the package registered its
     * own listener in boot() rather than register().
     */
    public function boot(): void
    {
        $this->app['events']->listen(MessageSending::class, function (MessageSending $event) {
            if (config('mailog_test.boot_early')) {
                return $event->message->getHeaders()->addTextHeader('X-Host-Boot', '1');
            }
        });
    }
}
