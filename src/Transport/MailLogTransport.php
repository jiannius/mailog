<?php

namespace Jiannius\Mailog\Transport;

use Jiannius\Mailog\Listeners\MailLogListener;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps the host's real Symfony transport so a send-time failure can be
 * recorded. Laravel fires no "message failed" event — the transport simply
 * throws — so the only place to observe a synchronous send error is around the
 * transport's send() call. On failure we flip the pending log to FAILED, then
 * rethrow so the host's send fails exactly as it would without the package.
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
     * Send via the inner transport, logging the error if it throws.
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            return $this->inner->send($message, $envelope);
        } catch (\Throwable $e) {
            $this->listener->failed($message, $e);

            throw $e;
        }
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
