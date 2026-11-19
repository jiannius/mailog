<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope as MailableEnvelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Listeners\MailLogListener;
use Jiannius\Mailog\Mailog;
use Jiannius\Mailog\Models\MailLog;
use Jiannius\Mailog\Transport\MailLogTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

uses(RefreshDatabase::class);

it('wraps the configured mailer transport on its first send', function () {
    Mail::html('<p>hi</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('First'));

    expect(Mail::getSymfonyTransport())->toBeInstanceOf(MailLogTransport::class);
});

it('stays transparent so the inner transport remains usable', function () {
    // __call forwards unknown methods (here ArrayTransport::messages()) to the inner transport.
    Mail::html('<p>hi</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('OK'));

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(1);
    expect(MailLog::sole()->status)->toBe(Status::SENT);
});

it('marks the log failed and records the error when the transport throws', function () {
    // A mailer whose transport always throws, wrapped the way sending() lazily wraps real mailers.
    Mail::extend('throwing', fn () => new ThrowingTransport);
    config()->set('mail.mailers.throwing', ['transport' => 'throwing']);

    $mailer = Mail::mailer('throwing');
    $mailer->setSymfonyTransport(
        new MailLogTransport($mailer->getSymfonyTransport())
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

it('marks the log sent when the real transport clones the message', function () {
    // Real Symfony transports (SMTP, Mailgun, SES) clone the message in AbstractTransport::send(),
    // so MessageSent carries a different object than MessageSending saw. NullTransport clones too.
    $mailer = Mail::mailer('array');
    $mailer->setSymfonyTransport($transport = new MailLogTransport(new NullTransport));

    $sentMessages = [];
    Event::listen(MessageSent::class, function (MessageSent $event) use (&$sentMessages) {
        $sentMessages[] = $event->sent;
    });

    $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Cloned'));

    $log = MailLog::sole();

    expect($log->status)->toBe(Status::SENT);
    expect($log->sent_at)->not->toBeNull();
    expect($log->failed_at)->toBeNull();

    // MessageSent fired with the transport's SentMessage; the id the log holds is that one.
    expect($sentMessages)->toHaveCount(1);
    expect($log->message_id)->not->toBeNull()->toBe($sentMessages[0]->getMessageId());
});

it('writes the sent status once when the transport does not clone the message', function () {
    // With a non-cloning transport MessageSent carries the very object the pending log is keyed by,
    // so the sent() fallback would write a second time unless delivered() cleared the entry first.
    $mailer = Mail::mailer('array');
    $mailer->setSymfonyTransport(new MailLogTransport(new KeepTransport));

    $updates = 0;
    DB::listen(function ($query) use (&$updates) {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updates++;
        }
    });

    $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Kept'));

    expect(MailLog::sole()->status)->toBe(Status::SENT);
    expect($updates)->toBe(1);
});

it('marks the log cancelled when the transport rejects the message', function () {
    // Symfony's AbstractTransport::send() returns null when a MessageEvent listener rejects the message.
    $mailer = Mail::mailer('array');
    $mailer->setSymfonyTransport(new MailLogTransport(new RejectingTransport));

    $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Rejected'));

    $log = MailLog::sole();
    expect($log->status)->toBe(Status::CANCELLED);
    expect($log->sent_at)->toBeNull();
    expect($log->failed_at)->toBeNull();
    expect($log->error)->toBeNull();
    expect($log->message_id)->toBeNull();
});

it('creates no row when a host listener registered during boot cancels the send', function () {
    // HostCancelServiceProvider registers its listener in boot(), like a real app's provider does.
    config()->set('mailog_test.cancel', true);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Blocked'));

    expect(MailLog::count())->toBe(0);
    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(0);
});

it('creates no row when a listener registered at runtime cancels the send', function () {
    // Registered after the package's listener, which therefore ran first; the cancel still means
    // the transport is never reached, so the row is never inserted.
    Event::listen(MessageSending::class, fn () => false);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Late'));

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(0);
    expect(MailLog::count())->toBe(0);
});

it('still logs a mail whose sending chain an earlier non-null listener stopped', function () {
    // HostEarlyServiceProvider's listener runs before the package's and returns a non-null value, so
    // until() never reaches the package's listener; the transport logs the mail from the message alone.
    config()->set('mailog_test.early', true);

    Mail::to('t@example.com')->send(new FailureTestMailable);

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(1);

    $log = MailLog::sole();
    expect($log->status)->toBe(Status::SENT);
    expect($log->mailer)->toBe('array');
    expect($log->subject)->toBe('From Failure Mailable');
    expect($log->mailable)->toBeNull();
    expect($log->message_id)->not->toBeNull();
});

it('honours the skip header when an earlier listener stopped the sending chain', function () {
    config()->set('mailog_test.early', true);

    Mail::html('<p>x</p>', function ($message) {
        $message->from('s@example.com')->to('t@example.com')->subject('Skipped');
        $message->getSymfonyMessage()->getHeaders()->addTextHeader(Mailog::SKIP_HEADER, '1');
    });

    $sent = Mail::getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);
    expect($sent[0]->getOriginalMessage()->getHeaders()->has(Mailog::SKIP_HEADER))->toBeFalse();
    expect(MailLog::count())->toBe(0);
});

it('honours the opt-outs when an earlier listener stopped the sending chain', function () {
    config()->set('mailog_test.early', true);

    config()->set('mailog.enabled', false);
    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Off'));

    config()->set('mailog.enabled', true);
    config()->set('mailog.except.mailers', ['array']);
    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Excluded'));

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(2);
    expect(MailLog::count())->toBe(0);
});

it('wraps the mailer transport exactly once across several sends', function () {
    foreach (range(1, 3) as $i) {
        Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject("Send {$i}"));
    }

    $transport = Mail::getSymfonyTransport();

    expect($transport)->toBeInstanceOf(MailLogTransport::class);
    expect($transport->messages())->toHaveCount(3);
    expect((fn () => $this->inner)->call($transport))->not->toBeInstanceOf(MailLogTransport::class);
    expect(MailLog::where('status', Status::SENT->value)->count())->toBe(3);
});

it('logs a non-cloning mailer built on demand under a configured name via the sent fallback', function () {
    $mailer = Mail::build(['name' => 'array', 'transport' => 'array']);

    $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Built'));

    // The built Mailer is not Laravel's cached instance, so its transport never wrapped and never
    // recorded the attempt: the MessageSent handler inserts the row from the stashed attributes.
    expect($mailer->getSymfonyTransport())->not->toBeInstanceOf(MailLogTransport::class);
    $log = MailLog::sole();
    expect($log->status)->toBe(Status::SENT);
    expect($log->message_id)->not->toBeNull();
    expect($log->sent_at)->not->toBeNull();
});

it('logs a non-cloning send through a mailer held across a purge via the sent fallback', function () {
    // Purge first so the held Mailer is a fresh, unwrapped instance (boot wrapped the original).
    Mail::purge('array');
    $held = Mail::mailer('array');

    Mail::purge('array');

    $held->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Held'));

    // sending() wrapped the freshly resolved instance, not the held one that actually sent.
    expect($held->getSymfonyTransport())->not->toBeInstanceOf(MailLogTransport::class);
    $log = MailLog::sole();
    expect($log->status)->toBe(Status::SENT);
    expect($log->message_id)->not->toBeNull();
});

it('known limitation: a cloning mailer built on demand under a configured name gets no row', function () {
    registerCloningMailer();
    $sent = collectSentMessages();

    Mail::build(['name' => 'cloning', 'transport' => 'cloning'])
        ->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Lost'));

    // The mail was sent, but the stash is keyed by an object the cloning transport replaced.
    expect($sent())->toHaveCount(1);
    expect(MailLog::count())->toBe(0);
});

it('known limitation: a cloning mailer held across a purge gets no row', function () {
    registerCloningMailer();
    $sent = collectSentMessages();
    $held = Mail::mailer('cloning');

    Mail::purge('cloning');

    $held->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Lost'));

    expect($sent())->toHaveCount(1);
    expect(MailLog::count())->toBe(0);
});

it('keeps logging after the listener singleton is flushed while the wrapped transport survives', function () {
    registerCloningMailer();
    $send = fn () => Mail::mailer('cloning')->to('t@example.com')->send(new FailureTestMailable);

    $send();
    app()->forgetInstance(MailLogListener::class);
    $send();

    // The transport resolves the listener at send time, so it finds what the new singleton stashed;
    // a transport holding the old instance would rebuild the row from the message alone (no mailable).
    $logs = MailLog::orderBy('id')->get();
    expect($logs)->toHaveCount(2);
    expect($logs->pluck('status')->all())->toBe([Status::SENT, Status::SENT]);
    expect($logs->pluck('mailable')->all())->toBe([FailureTestMailable::class, FailureTestMailable::class]);
});

it('still logs and reaches sent after the mailers are purged', function () {
    Mail::purge();

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Purged'));

    expect(Mail::getSymfonyTransport())->toBeInstanceOf(MailLogTransport::class);
    expect(MailLog::sole()->status)->toBe(Status::SENT);
});

it('reaches sent through a cloning transport on a mailer that was never wrapped', function () {
    registerCloningMailer();

    Mail::mailer('cloning')->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Lazy'));

    $log = MailLog::sole();
    expect($log->mailer)->toBe('cloning');
    expect($log->status)->toBe(Status::SENT);
    expect($log->message_id)->not->toBeNull();
});

it('falls back to inserting on sending for on-demand mailers', function () {
    $mailer = Mail::build(['transport' => 'array']);

    $mailer->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('On demand'));

    // Not wrapped, so the row is inserted on MessageSending and flipped by the MessageSent fallback.
    expect($mailer->getSymfonyTransport())->not->toBeInstanceOf(MailLogTransport::class);
    $log = MailLog::sole();
    expect($log->mailer)->toBe('ondemand');
    expect($log->status)->toBe(Status::SENT);
});

it('leaves an on-demand mailer row pending when its transport clones the message', function () {
    Mail::extend('cloning', fn () => new NullTransport);

    Mail::build(['transport' => 'cloning'])
        ->html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('On demand clone'));

    expect(MailLog::sole()->status)->toBe(Status::PENDING);
});

it('creates no row for an opted-out message even on a wrapped mailer', function () {
    config()->set('mailog.except.mailers', ['array']);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Excluded'));

    expect(MailLog::count())->toBe(0);
    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(1);
});

/**
 * Register a mailer named "cloning" whose NullTransport clones the message, like a real transport.
 */
function registerCloningMailer(): void
{
    Mail::extend('cloning', fn () => new NullTransport);
    config()->set('mail.mailers.cloning', ['transport' => 'cloning']);
}

/**
 * Collect the SentMessages MessageSent fires with; call the returned closure for the list.
 */
function collectSentMessages(): Closure
{
    $sent = [];

    Event::listen(MessageSent::class, function (MessageSent $event) use (&$sent) {
        $sent[] = $event->sent;
    });

    return function () use (&$sent): array {
        return $sent;
    };
}

class FailureTestMailable extends Mailable
{
    public function envelope(): MailableEnvelope
    {
        return new MailableEnvelope(subject: 'From Failure Mailable');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>mailable</p>');
    }
}

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

class RejectingTransport implements TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return null;
    }

    public function __toString(): string
    {
        return 'rejecting';
    }
}

class KeepTransport implements TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'keep';
    }
}
