<?php

namespace Jiannius\Mailog\Transport;

use Jiannius\Mailog\Listeners\MailLogListener;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps the host's real Symfony transport so the send can be recorded. The
 * PENDING row is inserted here, just before the inner send — a message a
 * MessageSending listener cancelled never reaches the transport, so it gets no
 * row. Laravel fires no "message failed" event — the transport simply throws —
 * and no event at all when a message is rejected, so the only place to observe
 * these is around the transport's send() call. On success we flip the pending
 * log to SENT, on a null return to CANCELLED, and on a throw to FAILED (then
 * rethrow so the host's send fails exactly as it would without the package).
 */
class MailLogTransport implements TransportInterface
{
    /**
     * Decorate the inner transport with failure logging.
     */
    public function __construct(
        protected TransportInterface $inner,
        protected MailLogListener $listener,
    ) {}

    /**
     * Insert the pending log, then send via the inner transport, logging the
     * outcome: sent on success,
     * cancelled if it returns null (message rejected), failed (then rethrown)
     * if it throws. Marking sent here rather than from MessageSent is
     * deliberate — real Symfony transports clone the message, so the event's
     * original message is not the object the pending log is keyed by.
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->listener->attempting($message);

        try {
            $sent = $this->inner->send($message, $envelope);
        } catch (\Throwable $e) {
            $this->listener->failed($message, $e);

            throw $e;
        }

        $this->listener->delivered($message, $sent);

        return $sent;
    }

    /**
     * Forward any other transport method (e.g. ArrayTransport::messages()) to
     * the inner transport so the decorator stays transparent to host code.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }

    /**
     * Mirror the inner transport's string representation.
     */
    public function __toString(): string
    {
        return (string) $this->inner;
    }
}
