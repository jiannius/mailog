<?php

namespace Jiannius\Mailog\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Mailog;
use Jiannius\Mailog\Models\MailLog;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class MailLogListener
{
    /**
     * Maps each outgoing message object to its MailLog id, bridging sending →
     * sent. A WeakMap so entries vanish when the message is garbage-collected:
     * no leak (and no spl_object_id reuse hazard) in long-running workers when
     * a send fails before MessageSent fires.
     *
     * @var \WeakMap<object, string>
     */
    protected \WeakMap $pending;

    /**
     * Initialise the message → log id map.
     */
    public function __construct()
    {
        $this->pending = new \WeakMap;
    }

    /**
     * Create a pending log for an outgoing message.
     */
    public function sending(MessageSending $event): void
    {
        try {
            if (! config('mailog.enabled', true)) {
                return;
            }

            $message = $event->message;
            $headers = $message->getHeaders();

            if ($headers->has(Mailog::SKIP_HEADER)) {
                $headers->remove(Mailog::SKIP_HEADER);

                return;
            }

            $mailer = $event->data['mailer'] ?? null;
            $mailable = $event->data['__laravel_mailable'] ?? null;

            if ($mailer !== null && in_array($mailer, config('mailog.except.mailers', []), true)) {
                return;
            }

            if ($mailable !== null && in_array($mailable, config('mailog.except.mailables', []), true)) {
                return;
            }

            $mailog = app(Mailog::class);

            $log = new MailLog([
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
            ]);

            $log->fill($mailog->resolveUser($event));
            $log->fill($mailog->resolveData($event));

            $log->save();

            $this->pending[$message] = $log->getKey();
        } catch (\Throwable $e) {
            // Logging must never break the host's email send — report and move on.
            report($e);
        }
    }

    /**
     * Flip the pending log to sent once the transport accepts it.
     */
    public function sent(MessageSent $event): void
    {
        try {
            $message = $event->sent->getOriginalMessage();

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
