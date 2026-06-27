## Mailog

`jiannius/mailog` logs every outgoing email this app sends into the `mail_logs` table for audit
tracking. Its service provider auto-registers and listens to Laravel's mail events — logging is
**on by default with no code changes**. Run `php artisan migrate` after installing.

### What is logged

Each send creates a `Jiannius\Mailog\Models\MailLog` row: sender, recipients (to/cc/bcc/reply-to),
subject, both HTML and text bodies, attachment metadata (filename/mime/size), tags, metadata,
the mailer + Mailable class, the resolved user, and the outcome. A row is created `PENDING` on
send and flipped to `SENT` once the transport accepts it. If the transport throws at send time,
the row is flipped to `FAILED` with the error stored in the `error` column (and `failed_at`); the
original exception is rethrown, so your send fails exactly as it would without the package. A row
left `PENDING` means the send never completed (e.g. the process died mid-send).

@verbatim
<code-snippet name="Querying logs" lang="php">
use Jiannius\Mailog\Models\MailLog;

MailLog::sent()->latest()->get();
MailLog::failed()->get();           // transport threw at send time; see ->error
MailLog::pending()->get();          // in-flight / never completed
mailog()->version();                // package entry-point singleton
</code-snippet>
@endverbatim

### Opting out

@verbatim
<code-snippet name="Exclude emails from logging" lang="php">
// config/mailog.php
'enabled' => env('MAILOG_ENABLED', true),   // master switch
'except' => [
    'mailers' => ['log'],                   // skip whole mailers
    'mailables' => [App\Mail\OrderShipped::class],
],
</code-snippet>
@endverbatim

Per-email: add the `Jiannius\Mailog\Mailog::SKIP_HEADER` header on the message (it is stripped
before sending).

### Attaching the user and custom columns

Set resolvers in a service provider (cache-safe — do not put closures in config):

@verbatim
<code-snippet name="Resolvers" lang="php">
use Jiannius\Mailog\Mailog;

Mailog::resolveUserUsing(fn () => auth()->user());          // default; user_id + user_name snapshot
Mailog::resolveDataUsing(fn () => ['tenant_id' => tenant()->id]);
</code-snippet>
@endverbatim

For custom columns, publish the migration (`php artisan vendor:publish --tag=mailog-migrations`),
add the column (e.g. `$table->ulid('tenant_id')->nullable()->index();`), then return it from
`resolveDataUsing`. The `MailLog` model is unguarded, so returned keys fill real columns.

### Enums

Backed enums in this app may mix in `Jiannius\Mailog\Traits\Enum`; cases are `FULL_UPPERCASE`.
The trait provides `all()`, `option()`, `label()`, `get()`, and `is()`/`isNot()`.
