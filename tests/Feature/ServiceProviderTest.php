<?php

use Jiannius\Mailog\Mailog;

it('binds the mailog singleton and alias', function () {
    expect(app('mailog'))->toBeInstanceOf(Mailog::class);
    expect(app(Mailog::class))->toBe(app('mailog'));
});

it('exposes the mailog() helper returning the singleton', function () {
    expect(mailog())->toBeInstanceOf(Mailog::class);
    expect(mailog()->version())->toBeString()->not->toBeEmpty();
});

it('merges the package config so config(mailog.*) is available', function () {
    expect(config('mailog'))->toBeArray()->not->toBeEmpty();
});
