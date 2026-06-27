<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Listeners\MailLogListener;
use Jiannius\Mailog\Models\MailLog;
use Jiannius\Mailog\Transport\MailLogTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

uses(RefreshDatabase::class);

it('wraps the configured mailer transport at boot', function () {
    expect(Mail::getSymfonyTransport())->toBeInstanceOf(MailLogTransport::class);
});

it('stays transparent so the inner transport remains usable', function () {
    // __call forwards unknown methods (here ArrayTransport::messages()) to the inner transport.
    Mail::html('<p>hi</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('OK'));

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(1);
    expect(MailLog::sole()->status)->toBe(Status::SENT);
});

it('marks the log failed and records the error when the transport throws', function () {
    // A mailer whose transport always throws, wrapped exactly the way boot() wraps real mailers.
    Mail::extend('throwing', fn () => new ThrowingTransport);
    config()->set('mail.mailers.throwing', ['transport' => 'throwing']);

    $mailer = Mail::mailer('throwing');
    $mailer->setSymfonyTransport(
        new MailLogTransport($mailer->getSymfonyTransport(), app(MailLogListener::class))
    );

    $send = fn () => $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Fail'));

    // The host's send still throws exactly as it would without the package.
    expect($send)->toThrow(TransportException::class);

    $log = MailLog::sole();
    expect($log->status)->toBe(Status::FAILED);
    expect($log->error)->toContain('smtp down');
    expect($log->failed_at)->not->toBeNull();
    expect($log->sent_at)->toBeNull();
    expect($log->message_id)->toBeNull();
});

class ThrowingTransport implements TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new TransportException('smtp down');
    }

    public function __toString(): string
    {
        return 'throwing';
    }
}
