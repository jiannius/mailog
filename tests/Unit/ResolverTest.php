<?php

use Illuminate\Mail\Events\MessageSending;
use Jiannius\Mailog\Mailog;
use Symfony\Component\Mime\Email;

afterEach(function () {
    Mailog::resolveUserUsing(null);
    Mailog::resolveDataUsing(null);
});

function fakeSendingEvent(): MessageSending
{
    return new MessageSending(new Email, ['mailer' => 'array']);
}

it('resolves a user model to a getKey + name snapshot', function () {
    $user = new class
    {
        public string $name = 'Jane Doe';

        public function getKey(): int
        {
            return 42;
        }
    };

    Mailog::resolveUserUsing(fn () => $user);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))
        ->toBe(['user_id' => 42, 'user_name' => 'Jane Doe']);
});

it('resolves an explicit array user shape as-is', function () {
    Mailog::resolveUserUsing(fn () => ['id' => 'u-1', 'name' => 'Bob']);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))
        ->toBe(['user_id' => 'u-1', 'user_name' => 'Bob']);
});

it('returns no user attributes when the resolver yields null', function () {
    Mailog::resolveUserUsing(fn () => null);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))->toBe([]);
});

it('resolves host data and defaults to an empty array', function () {
    Mailog::resolveDataUsing(fn () => ['tenant_id' => 'acme']);
    expect(app(Mailog::class)->resolveData(fakeSendingEvent()))->toBe(['tenant_id' => 'acme']);

    Mailog::resolveDataUsing(null);
    expect(app(Mailog::class)->resolveData(fakeSendingEvent()))->toBe([]);
});

it('exposes the skip header constant', function () {
    expect(Mailog::SKIP_HEADER)->toBe('X-Mailog-Skip');
});
