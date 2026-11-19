<?php

namespace Jiannius\Mailog\Tests\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Stands in for a host app's service provider: registers a MessageSending
 * listener during boot that cancels the send while config('mailog_test.cancel')
 * is true.
 */
class HostCancelServiceProvider extends ServiceProvider
{
    /**
     * Register the cancelling listener the way a host app would, during boot.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, fn (): ?bool => config('mailog_test.cancel') ? false : null);
    }
}
