<?php

use Jiannius\Mailog\Traits\Enum;

enum MailogStatus: string
{
    use Enum;

    case ACTIVE = 'active';
    case PENDING = 'pending';
    case TRASHED = 'trashed';
}

it('lists cases, excluding TRASHED by default', function () {
    expect(MailogStatus::all()->pluck('value')->all())->toBe(['active', 'pending']);
    expect(MailogStatus::all(false))->toHaveCount(3);
});

it('builds an option array and a humanized label', function () {
    expect(MailogStatus::ACTIVE->option())->toBe(['value' => 'active', 'label' => 'Active']);
    expect(MailogStatus::PENDING->label())->toBe('Pending');
});

it('resolves a case from a name or value with get()', function () {
    expect(MailogStatus::get('active'))->toBe(MailogStatus::ACTIVE);
    expect(MailogStatus::get('ACTIVE'))->toBe(MailogStatus::ACTIVE);
    expect(MailogStatus::get(MailogStatus::PENDING))->toBe(MailogStatus::PENDING);
});

it('matches with is()/isNot()', function () {
    expect(MailogStatus::ACTIVE->is('active'))->toBeTrue();
    expect(MailogStatus::ACTIVE->is('active', 'pending'))->toBeTrue();
    expect(MailogStatus::ACTIVE->isNot('pending'))->toBeTrue();
});
