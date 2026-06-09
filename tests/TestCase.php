<?php

namespace Jiannius\Mailog\Tests;

use Jiannius\Mailog\MailogServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Register the package's service provider(s) into the test application.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MailogServiceProvider::class];
    }

    /**
     * Configure the Testbench environment (in-memory sqlite + app key).
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('mail.from', ['address' => 'noreply@example.com', 'name' => 'Test']);
    }
}
