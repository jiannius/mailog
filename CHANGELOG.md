# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.2.0] - 2026-11-19

- Mails cancelled by a `MessageSending` listener (returning `false`) no longer leave a `PENDING` row: the row is now inserted by `MailLogTransport` just before the send instead of on `MessageSending`.
- **Breaking:** the `MailLogTransport` constructor is now `(TransportInterface $inner, ?string $mailer = null)`; it resolves `MailLogListener` from the container at send time. Hosts that built it with a listener argument must drop it.
- Mailers are wrapped lazily on their first logged send instead of at boot (config changed after boot is honoured, and boot no longer reports missing stock drivers).
- The `MessageSending` / `MessageSent` listeners are registered in the service provider's `register()` instead of `boot()`, so they run ahead of listeners registered during boot.
- `X-Mailog-Skip` is now stripped on every path, including when logging is disabled or the mailer is excluded.

## [0.1.0] - 2026-11-19

- Mails sent through real transports (SMTP, Mailgun, SES) reach `SENT` (#1); transport-rejected mails are `CANCELLED` (#3).
- Minimal-by-default scaffold: example model/factory/migration/route/component/command removed in favor of README recipes + commented provider hooks.
- `version()` reads from Composer runtime metadata instead of a hardcoded string.
- `actions/checkout` bumped to v5 in CI workflows.
- Initial package mailog.
