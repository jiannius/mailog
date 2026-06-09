<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Models\MailLog;

uses(RefreshDatabase::class);

it('persists a mail log via the factory with casts and a ulid key', function () {
    $log = MailLog::factory()->create();

    expect($log->getKey())->toBeString()->toHaveLength(26);
    expect($log->status)->toBe(Status::PENDING);
    expect($log->from)->toBeArray();
    expect(MailLog::pending()->count())->toBe(1);
});

it('exposes the sent factory state and scope', function () {
    MailLog::factory()->sent()->create();

    $log = MailLog::sent()->sole();
    expect($log->status)->toBe(Status::SENT);
    expect($log->sent_at)->not->toBeNull();
});
