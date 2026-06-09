# Jiannius Mailog

Audit-log every outgoing email your Laravel app sends — straight to the database, with no code
changes. Mailog listens to Laravel's mail events and records each message (sender, recipients,
subject, bodies, attachments, tags, the originating mailer/Mailable, the acting user, and the
send outcome) in a `mail_logs` table.

## Requirements

- PHP 8.3+
- Laravel 13

## Installation

```bash
composer require jiannius/mailog
php artisan migrate
```

> Not on Packagist yet — until it is, add a VCS repository to the host app's `composer.json`:
> ```json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/jiannius/mailog" }
> ],
> "require": { "jiannius/mailog": "dev-main" }
> ```

The service provider auto-registers and the migration auto-loads, so `php artisan migrate`
creates the `mail_logs` table. From that point **every outgoing email is logged** — `Mail::send`,
Mailables, framework notifications, queued mail, all of it. No further wiring.

## What gets logged

Each send creates one `Jiannius\Mailog\Models\MailLog` row:

| Column | Contents |
| --- | --- |
| `status` | `pending` → `sent` (a row left `pending` means the send failed) |
| `mailer` | the mailer name (e.g. `smtp`, `ses`) |
| `mailable` | the Mailable class, when sent via one |
| `user_id` / `user_name` | the resolved acting user + a name snapshot (see below) |
| `from` / `to` / `cc` / `bcc` / `reply_to` | `[{address, name}]` arrays |
| `subject` | the subject line |
| `html_body` / `text_body` | the full rendered bodies |
| `attachments` | `[{filename, mime, size}]` metadata (no file bytes) |
| `tags` / `metadata` | Symfony tag + metadata headers (`Mailable::tag()` / `metadata()`) |
| `message_id` | the transport message id, set once sent |
| `sent_at` | timestamp the transport accepted the message |

A row is created `pending` when the message is about to send and flipped to `sent` once the
transport accepts it. If the transport throws, the `sent` step never runs and the row stays
`pending` — giving you failure visibility for free.

## Querying logs

```php
use Jiannius\Mailog\Models\MailLog;

MailLog::sent()->latest()->get();          // confirmed sent
MailLog::pending()->get();                 // in-flight or failed
MailLog::where('to', 'like', '%@acme.com%')->get();
```

`status` is cast to `Jiannius\Mailog\Enums\Status` (`PENDING`, `SENT`, `FAILED`). `FAILED` is
never set automatically — it's reserved for you to set from a bounce/complaint webhook.

## Configuration

Logging works with no config. To customise, publish the file:

```bash
php artisan vendor:publish --tag=mailog-config
```

```php
// config/mailog.php
return [
    'enabled' => env('MAILOG_ENABLED', true),   // master switch
    'table'   => 'mail_logs',
    'except'  => [
        'mailers'   => [],   // mailer names to skip, e.g. ['log', 'array']
        'mailables' => [],   // Mailable classes to skip, e.g. [App\Mail\OrderShipped::class]
    ],
];
```

## Opting out

Logging is on by default; opt specific email out three ways:

- **Master switch** — `MAILOG_ENABLED=false` (or `config('mailog.enabled')`) turns all logging off.
- **Config exclude** — list mailer names in `mailog.except.mailers` or Mailable classes in
  `mailog.except.mailables`.
- **Per-email** — add the `X-Mailog-Skip` header to a message. Mailog skips it and strips the
  header before the message is sent:

  ```php
  use Jiannius\Mailog\Mailog;

  // in a Mailable's headers(), or via ->withSymfonyMessage(...)
  $message->getHeaders()->addTextHeader(Mailog::SKIP_HEADER, '1');
  ```

## Attaching the acting user

Each log records `user_id` + a `user_name` **snapshot** (so the log survives the user being
deleted — there is no foreign key). By default the user is `auth()->user()`. Override the
resolver in a service provider (cache-safe — never put a closure in a config file):

```php
use Jiannius\Mailog\Mailog;

public function boot(): void
{
    // Return a model (uses getKey() + ->name), an ['id' => ..., 'name' => ...] array, or null.
    Mailog::resolveUserUsing(fn () => auth()->user());
}
```

Returning `null` (guests, queued jobs, console) leaves both columns null.

## Custom columns (multi-tenant, etc.)

The `MailLog` model is unguarded, so you can attach your own columns — e.g. a `tenant_id` for a
multi-tenant app:

1. Publish and edit the migration:

   ```bash
   php artisan vendor:publish --tag=mailog-migrations
   ```

   ```php
   $table->ulid('tenant_id')->nullable()->index();
   ```

2. Feed the value through the data resolver in a service provider:

   ```php
   use Jiannius\Mailog\Mailog;

   Mailog::resolveDataUsing(fn () => ['tenant_id' => tenant()->id]);
   ```

The returned array is filled onto the log, so the value lands in your real, indexed column:
`MailLog::where('tenant_id', $id)->get()`.

## How it works

Mailog subscribes to Laravel's `MessageSending` and `MessageSent` events. The first creates the
`pending` row and extracts every field from the Symfony `Email`; the second flips it to `sent`
with the transport's message id. The two events are correlated by the message object itself (via
a `WeakMap`), so logging is reliable even in long-running workers (Octane, `queue:work`).

Capture is wrapped so that **a logging failure can never break your application's email** — any
exception while logging is reported and swallowed; the email still sends.

## Development

This is a Composer library, so it has no `artisan` binary — Orchestra Testbench provides one
(`vendor/bin/testbench` boots a throwaway Laravel app configured by `testbench.yaml`).

```bash
composer install
composer test                                   # Pest 4 + Testbench suite
vendor/bin/pest tests/Feature/MailLogTest.php   # one file
composer lint                                   # Pint
```

Tests run against in-memory sqlite and extend `Tests\TestCase` (wired automatically via
`tests/Pest.php`).

## License

MIT.
