<?php

namespace Jiannius\Mailog\Tests\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Stands in for a host app listener that runs before the package's: while
 * config('mailog_test.early') is true it returns a non-null value (the result
 * of adding a header), which makes Laravel's until() stop the MessageSending
 * chain before the package's listener runs. Listed before the package provider
 * in TestCase so it registers first.
 */
class HostEarlyServiceProvider extends ServiceProvider
{
    /**
     * Register the chain-stopping listener the way a host app would, during boot.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            if (config('mailog_test.early')) {
                return $event->message->getHeaders()->addTextHeader('X-Host-Early', '1');
            }
        });
    }
}
