<?php

use Jiannius\Mailog\Enums\Status;

it('exposes the mail-log statuses via the Enum trait', function () {
    expect(Status::all()->pluck('value')->all())->toBe(['pending', 'sent', 'failed']);
    expect(Status::PENDING->label())->toBe('Pending');
    expect(Status::get('sent'))->toBe(Status::SENT);
    expect(Status::SENT->is('sent'))->toBeTrue();
});
