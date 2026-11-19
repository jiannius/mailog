<?php

namespace Jiannius\Mailog\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Mailog;
use Jiannius\Mailog\Models\MailLog;
use Jiannius\Mailog\Transport\MailLogTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class MailLogListener
{
    /**
     * Maps each outgoing message object to the attributes of a log built on
     * sending but not yet inserted. MailLogTransport inserts the row just
     * before the transport sends, so a send cancelled in between (a
     * MessageSending listener returning false) never gets one. A WeakMap so
     * entries vanish when the message is garbage-collected: no leak (and no
     * spl_object_id reuse hazard) in long-running workers.
     *
     * @var \WeakMap<object, array<string, mixed>>
     */
    protected \WeakMap $stashed;

    /**
     * Messages that were opted out on sending (skip header, except lists), so
     * the transport must not log them from the message alone.
     *
     * @var \WeakMap<object, true>
     */
    protected \WeakMap $skipped;

    /**
     * Maps each outgoing message object to the id of its PENDING MailLog,
     * bridging the insert → the send outcome. Same WeakMap rationale as
     * $stashed.
     *
     * @var \WeakMap<object, string>
     */
    protected \WeakMap $pending;

    /**
     * Initialise the message-keyed maps.
     */
    public function __construct()
    {
        $this->stashed = new \WeakMap;
        $this->skipped = new \WeakMap;
        $this->pending = new \WeakMap;
    }

    /**
     * Build the log for an outgoing message and make sure the named mailer's
     * transport is wrapped. With a wrapped mailer the attributes are stashed
     * for MailLogTransport to insert just before the send; otherwise (on-demand
     * or unresolvable mailers) the PENDING row is inserted now.
     */
    public function sending(MessageSending $event): void
    {
        try {
            $message = $event->message;
            $headers = $message->getHeaders();
            $skip = $headers->has(Mailog::SKIP_HEADER);

            // Never let the skip header reach the transport, whether or not we log.
            if ($skip) {
                $headers->remove(Mailog::SKIP_HEADER);
            }

            if (! config('mailog.enabled', true)) {
                return;
            }

            if ($skip) {
                $this->skipped[$message] = true;

                return;
            }

            $mailer = $event->data['mailer'] ?? null;
            $mailable = $event->data['__laravel_mailable'] ?? null;

            if ($mailer !== null && in_array($mailer, config('mailog.except.mailers', []), true)) {
                $this->skipped[$message] = true;

                return;
            }

            if ($mailable !== null && in_array($mailable, config('mailog.except.mailables', []), true)) {
                $this->skipped[$message] = true;

                return;
            }

            $attributes = $this->attributes($event, $mailer, $mailable);

            if ($this->wrap($mailer)) {
                $this->stashed[$message] = $attributes;

                return;
            }

            $this->insert($message, $attributes);
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Record the attempt just before the transport sends: insert the PENDING
     * log from the attributes stashed on sending.
     *
     * Called by MailLogTransport with the same message object MessageSending
     * saw. A no-op for opted-out messages and for messages already inserted.
     * When nothing is known about the message, an earlier MessageSending
     * listener returned a non-null value and stopped the chain before ours
     * ran; the log is then built from the message alone (no mailable) with the
     * opt-outs re-applied here.
     */
    public function attempting(object $message, ?string $mailer = null): void
    {
        try {
            if (isset($this->skipped[$message]) || isset($this->pending[$message])) {
                return;
            }

            if (isset($this->stashed[$message])) {
                $attributes = $this->stashed[$message];

                unset($this->stashed[$message]);

                $this->insert($message, $attributes);

                return;
            }

            if (! $message instanceof Email) {
                return;
            }

            // Strip first: the header must never be sent, whichever opt-out applies.
            $skip = $message->getHeaders()->has(Mailog::SKIP_HEADER);

            if ($skip) {
                $message->getHeaders()->remove(Mailog::SKIP_HEADER);
            }

            if (! config('mailog.enabled', true) || $skip) {
                return;
            }

            if ($mailer !== null && in_array($mailer, config('mailog.except.mailers', []), true)) {
                return;
            }

            $event = new MessageSending($message, ['message' => $message, 'mailer' => $mailer]);

            $this->insert($message, $this->attributes($event, $mailer, null));
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Build the attributes of a PENDING log from the message, with the user and
     * data resolvers run now: auth context belongs to this moment.
     *
     * @return array<string, mixed>
     */
    protected function attributes(MessageSending $event, ?string $mailer, ?string $mailable): array
    {
        $message = $event->message;
        $mailog = app(Mailog::class);

        return [
            'status' => Status::PENDING,
            'mailer' => $mailer,
            'mailable' => $mailable,
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'reply_to' => $this->addresses($message->getReplyTo()),
            'subject' => $message->getSubject(),
            'html_body' => $this->body($message->getHtmlBody()),
            'text_body' => $this->body($message->getTextBody()),
            'attachments' => $this->attachments($message),
            'tags' => $this->tags($message),
            'metadata' => $this->metadata($message),
            ...$mailog->resolveUser($event),
            ...$mailog->resolveData($event),
        ];
    }

    /**
     * Insert a PENDING log and remember its id against the message.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function insert(object $message, array $attributes): void
    {
        $log = new MailLog($attributes);

        $log->save();

        $this->pending[$message] = $log->getKey();
    }

    /**
     * Make sure the named mailer's transport is a MailLogTransport, wrapping it
     * if not, and report whether the mailer is now wrapped. Laravel reads the
     * mailer's transport after MessageSending, so the wrap applies to the send
     * in flight. False for on-demand or unconfigured mailers, or if wrapping
     * failed. Never throws.
     */
    protected function wrap(?string $name): bool
    {
        try {
            if ($name === null || ! array_key_exists($name, config('mail.mailers', []))) {
                return false;
            }

            $mailer = app('mail.manager')->mailer($name);
            $transport = $mailer->getSymfonyTransport();

            if (! $transport instanceof MailLogTransport) {
                $mailer->setSymfonyTransport(new MailLogTransport($transport, $name));
            }

            return true;
        } catch (\Throwable $e) {
            // A mailer we can't wrap must never break the host's send — report and move on.
            report($e);

            return false;
        }
    }

    /**
     * Flip the pending log to sent once the transport accepts it.
     *
     * Fallback for mailers not wrapped by MailLogTransport; a no-op when
     * delivered() already handled the message. When only stashed attributes
     * exist — the sending Mailer is not the manager's cached instance, so its
     * transport never recorded the attempt — the log is inserted as SENT.
     */
    public function sent(MessageSent $event): void
    {
        try {
            $message = $event->sent->getOriginalMessage();

            if (isset($this->stashed[$message])) {
                $attributes = $this->stashed[$message];

                unset($this->stashed[$message]);

                (new MailLog([
                    ...$attributes,
                    'status' => Status::SENT,
                    'message_id' => $event->sent->getMessageId(),
                    'sent_at' => now(),
                ]))->save();

                return;
            }

            if (! isset($this->pending[$message])) {
                return;
            }

            $id = $this->pending[$message];

            unset($this->pending[$message]);

            MailLog::query()->whereKey($id)->update([
                'status' => Status::SENT->value,
                'message_id' => $event->sent->getMessageId(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Flip the pending log after the transport returns: sent when it accepted
     * the message, cancelled when it returned null (a MessageEvent listener
     * rejected the message, so nothing was sent and Laravel fires no MessageSent).
     *
     * Called by MailLogTransport with the same message object MessageSending
     * saw, since real transports clone it before MessageSent fires. A no-op
     * when the message has no pending log.
     */
    public function delivered(object $message, ?SentMessage $sent): void
    {
        try {
            if (! isset($this->pending[$message])) {
                return;
            }

            $id = $this->pending[$message];

            unset($this->pending[$message]);

            $attributes = $sent === null
                ? ['status' => Status::CANCELLED->value]
                : [
                    'status' => Status::SENT->value,
                    'message_id' => $sent->getMessageId(),
                    'sent_at' => now(),
                ];

            MailLog::query()->whereKey($id)->update($attributes);
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Flip the pending log to failed when the transport throws on send.
     *
     * Called by MailLogTransport, not by an event (Laravel has none for a
     * failed send). A no-op when the message has no pending log — e.g. it was
     * skipped/excluded, or logging is disabled.
     */
    public function failed(object $message, \Throwable $e): void
    {
        try {
            if (! isset($this->pending[$message])) {
                return;
            }

            $id = $this->pending[$message];

            unset($this->pending[$message]);

            MailLog::query()->whereKey($id)->update([
                'status' => Status::FAILED->value,
                'error' => $e::class.': '.$e->getMessage(),
                'failed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Normalize an array of Symfony addresses to [{address, name}].
     *
     * @param  array<int, Address>  $addresses
     * @return array<int, array{address: string, name: ?string}>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => [
            'address' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ], $addresses);
    }

    /**
     * Collect attachment metadata (no bytes stored).
     *
     * @return array<int, array{filename: ?string, mime: string, size: int}>
     */
    protected function attachments(Email $message): array
    {
        return array_map(fn (DataPart $part): array => [
            'filename' => $part->getFilename(),
            'mime' => $part->getContentType(),
            'size' => strlen($part->getBody()),
        ], $message->getAttachments());
    }

    /**
     * Collect Symfony tag headers.
     *
     * @return array<int, string>
     */
    protected function tags(Email $message): array
    {
        $tags = [];

        foreach ($message->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            }
        }

        return $tags;
    }

    /**
     * Collect Symfony metadata headers as key => value.
     *
     * @return array<string, string>
     */
    protected function metadata(Email $message): array
    {
        $metadata = [];

        foreach ($message->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            }
        }

        return $metadata;
    }

    /**
     * Read a message body part (which may be a stream) to a string.
     */
    protected function body(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            return stream_get_contents($body) ?: null;
        }

        return (string) $body;
    }
}
