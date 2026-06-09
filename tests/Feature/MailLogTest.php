<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Mailog;
use Jiannius\Mailog\Models\MailLog;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

uses(RefreshDatabase::class);

afterEach(function () {
    Mailog::resolveUserUsing(null);
    Mailog::resolveDataUsing(null);
});

it('logs a sent email with all captured fields and the resolved user', function () {
    Mailog::resolveUserUsing(fn () => ['id' => 'u-9', 'name' => 'Ops']);

    Mail::html('<p>Hello HTML</p>', function ($message) {
        $message->from('sender@example.com', 'Sender')
            ->to('to@example.com', 'Recipient')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Welcome');
    });

    $log = MailLog::sole();

    expect($log->status)->toBe(Status::SENT);
    expect($log->mailer)->toBe('array');
    expect($log->subject)->toBe('Welcome');
    expect($log->html_body)->toContain('Hello HTML');
    expect($log->from)->toBe([['address' => 'sender@example.com', 'name' => 'Sender']]);
    expect($log->to)->toBe([['address' => 'to@example.com', 'name' => 'Recipient']]);
    expect($log->cc)->toBe([['address' => 'cc@example.com', 'name' => null]]);
    expect($log->bcc)->toBe([['address' => 'bcc@example.com', 'name' => null]]);
    expect($log->reply_to)->toBe([['address' => 'reply@example.com', 'name' => null]]);
    expect((string) $log->user_id)->toBe('u-9');
    expect($log->user_name)->toBe('Ops');
    expect($log->message_id)->not->toBeNull();
    expect($log->sent_at)->not->toBeNull();
});

it('logs the plain-text body for raw mail', function () {
    Mail::raw('Plain text body', function ($message) {
        $message->from('sender@example.com')->to('to@example.com')->subject('Raw');
    });

    $log = MailLog::sole();
    expect($log->text_body)->toContain('Plain text body');
    expect($log->html_body)->toBeNull();
});

it('logs attachment metadata only', function () {
    Mail::raw('body', function ($message) {
        $message->from('sender@example.com')->to('to@example.com')->subject('Att')
            ->attachData('PDFBYTES', 'report.pdf', ['mime' => 'application/pdf']);
    });

    $attachments = MailLog::sole()->attachments;
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]['filename'])->toBe('report.pdf');
    expect($attachments[0]['mime'])->toBe('application/pdf');
    expect($attachments[0]['size'])->toBe(strlen('PDFBYTES'));
});

it('captures tag and metadata headers', function () {
    Mail::html('<p>hi</p>', function ($message) {
        $message->from('sender@example.com')->to('to@example.com')->subject('Tagged');

        $headers = $message->getSymfonyMessage()->getHeaders();
        $headers->add(new TagHeader('welcome'));
        $headers->add(new TagHeader('onboarding'));
        $headers->add(new MetadataHeader('order_id', '42'));
    });

    $log = MailLog::sole();
    expect($log->tags)->toBe(['welcome', 'onboarding']);
    expect($log->metadata)->toBe(['order_id' => '42']);
});

it('records the originating mailable class', function () {
    Mail::to('to@example.com')->send(new MailogTestMailable);

    expect(MailLog::sole()->mailable)->toBe(MailogTestMailable::class);
});

it('skips excluded mailable classes', function () {
    config()->set('mailog.except.mailables', [MailogTestMailable::class]);

    Mail::to('to@example.com')->send(new MailogTestMailable);

    expect(MailLog::count())->toBe(0);
});

it('skips excluded mailers', function () {
    config()->set('mailog.except.mailers', ['array']);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('X'));

    expect(MailLog::count())->toBe(0);
});

it('logs nothing when disabled', function () {
    config()->set('mailog.enabled', false);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('X'));

    expect(MailLog::count())->toBe(0);
});

it('skips and strips the X-Mailog-Skip header', function () {
    $email = null;

    Mail::html('<p>x</p>', function ($message) use (&$email) {
        $message->from('s@example.com')->to('t@example.com')->subject('Skip');
        $email = $message->getSymfonyMessage();
        $email->getHeaders()->addTextHeader(Mailog::SKIP_HEADER, '1');
    });

    expect(MailLog::count())->toBe(0);
    expect($email->getHeaders()->has(Mailog::SKIP_HEADER))->toBeFalse();
});

it('fills host custom columns via the data resolver', function () {
    Schema::table('mail_logs', fn ($table) => $table->string('tenant_id')->nullable());

    Mailog::resolveDataUsing(fn () => ['tenant_id' => 'acme']);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('D'));

    expect(MailLog::sole()->tenant_id)->toBe('acme');
});

it('never breaks the email send when capture throws', function () {
    Mailog::resolveDataUsing(fn () => throw new RuntimeException('boom'));

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Boom'));

    expect(MailLog::count())->toBe(0);                               // capture failed and was swallowed
    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(1); // but the email still sent
});

it('snapshots the user as plain columns so logs survive a deleted user', function () {
    // A user resolved only for this send; nothing persists it to a users table.
    Mailog::resolveUserUsing(fn () => ['id' => '99', 'name' => 'Alice']);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('U'));

    // user_id/user_name are plain stored values (the migration defines no FK/relation),
    // so re-reading the log still yields the snapshot independent of any user record.
    $log = MailLog::sole()->fresh();
    expect($log->user_id)->toBe('99');
    expect($log->user_name)->toBe('Alice');
});

class MailogTestMailable extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'From Mailable');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>From mailable</p>');
    }
}
