# Outgoing Email Audit Logging — Design

**Date:** 2026-06-09
**Status:** Approved

## Goal

Turn `jiannius/mailog` into a working package that **logs every outgoing email a host
Laravel app sends into a database table for audit tracking**. Logging is on by default with
zero host code changes; the host can opt specific emails out. Each log captures sender,
recipients (to/cc/bcc/reply-to), subject, both message bodies, attachment metadata, tags,
metadata, the originating mailer + mailable, the resolved host user, and the send outcome.

## Decisions (locked)

| Decision | Choice |
| --- | --- |
| Capture mechanism | **Global event listeners** on Laravel `MessageSending` + `MessageSent` — logs everything (Mail::send, Mailables, notifications, queued mail) with no host code |
| Default behaviour | **Log on by default**; opt-out only |
| Opt-out: master switch | `config('mailog.enabled')` (+ `MAILOG_ENABLED` env) — always present |
| Opt-out: config exclude | `config('mailog.except.mailers')` and `config('mailog.except.mailables')` |
| Opt-out: per-email | `X-Mailog-Skip` header on the message (stripped before send) |
| Outcomes | **Sent + failed**: row created `PENDING` on sending, flipped to `SENT` on sent; a row left `PENDING` = in-flight or failed |
| Body storage | **Both HTML and plain-text bodies, full** (no truncation) |
| Attachments | **Metadata only** — `filename`, `mime`, `size`; no bytes stored |
| User association | nullable `user_id` (string, no FK) + `user_name` snapshot, via an **overridable resolver** defaulting to `auth()->user()` |
| Host custom fields | **Real columns only** — unguarded `MailLog` + publishable migration + `Mailog::resolveDataUsing()` hook; no json catch-all column |
| Robustness | Capture is wrapped in try/catch and reported, never rethrown — **logging must never break the host's email send** |
| Migration | **Auto-loaded** (`loadMigrationsFrom`) **and publishable** (`--tag=mailog-migrations`) |

## Why this capture mechanism

Verified against the installed Laravel framework v13 source:

- `Illuminate\Mail\Mailer::send()` sets `$data['mailer'] = $this->name` (`Mailer.php:303`) and
  dispatches `MessageSending($message, $data)` then `MessageSent($sent, $data)` **synchronously
  within the same `send()` call**, around the transport send.
- `Illuminate\Mail\Mailable::buildViewData()` adds `'__laravel_mailable' => get_class($this)`
  (`Mailable.php:400`), so the mailable class is available in `$data` for Mailable-based sends
  (absent for `Mail::raw` / `Mail::html`).
- Both events reference the **same** `Symfony\Component\Mime\Email` object — `MessageSending`
  exposes `$message`, and `MessageSent` exposes `$sent->getOriginalMessage()`. This lets the
  listener correlate the two events by `spl_object_id()` without mutating the message.
- Tags/metadata are `Symfony\Component\Mailer\Header\TagHeader` and `MetadataHeader` instances
  on the message headers (added by `Mailable::tag()` / `metadata()`).
- The transport message id is `$event->sent->getMessageId()`.

The listener does all field extraction in `MessageSending` (everything is available there) and
only updates status/`message_id`/`sent_at` in `MessageSent`. This makes failure capture free:
if the transport throws, `MessageSent` never fires and the row stays `PENDING`.

## Component designs

### Config — `config/mailog.php`

```php
return [
    'enabled' => env('MAILOG_ENABLED', true),
    'table'   => 'mail_logs',
    'except'  => [
        'mailers'   => [],   // mailer names to skip, e.g. ['log', 'array']
        'mailables' => [],   // Mailable classes to skip, e.g. [App\Mail\OrderShipped::class]
    ],
];
```

No closures in config (so `config:cache` is safe). The user resolver is set in code, not config.

### Status enum — `src/Enums/Status.php`

`Jiannius\Mailog\Enums\Status` — string-backed, mixes in `Jiannius\Mailog\Traits\Enum`,
`FULL_UPPERCASE` cases:

```php
case PENDING = 'pending';
case SENT    = 'sent';
case FAILED  = 'failed';
```

`PENDING` and `SENT` are set automatically. `FAILED` is reserved for hosts to set later (e.g.
from a bounce webhook) — not written by the capture path.

### Model — `src/Models/MailLog.php`

`HasUlids`, table from `config('mailog.table', 'mail_logs')`, package factory wired via
`newFactory()` (`Jiannius\Mailog\Database\Factories\MailLogFactory`).

Columns:

| column | type | source |
| --- | --- | --- |
| `id` | ulid pk | generated |
| `status` | string (cast `Status`) | `pending` → `sent` |
| `mailer` | string null | `$data['mailer']` |
| `mailable` | string null | `$data['__laravel_mailable']` |
| `user_id` | string null (no FK) | resolver → `getKey()` |
| `user_name` | string null | resolver → name snapshot |
| `from` | json null | `Email::getFrom()` as `[{address,name}]` |
| `to` | json null | `Email::getTo()` |
| `cc` | json null | `Email::getCc()` |
| `bcc` | json null | `Email::getBcc()` |
| `reply_to` | json null | `Email::getReplyTo()` |
| `subject` | string null | `Email::getSubject()` |
| `html_body` | longText null | `Email::getHtmlBody()` |
| `text_body` | longText null | `Email::getTextBody()` |
| `attachments` | json null | `[{filename, mime, size}]` from `Email::getAttachments()` |
| `tags` | json null | `TagHeader` values |
| `metadata` | json null | `MetadataHeader` key⇒value |
| `message_id` | string null | `$sent->getMessageId()` (set on sent) |
| `sent_at` | timestamp null | set on sent |
| `created_at` / `updated_at` | timestamps | |

Casts: `status` ⇒ `Status`, all json columns ⇒ `array`, `sent_at` ⇒ `datetime`.
The model is **unguarded** (`$guarded = []`) so a host can add real columns (e.g. `tenant_id`)
via the published migration and have the data resolver mass-assign them — there is no json
catch-all column. Query scopes: `pending()`, `sent()`. `user_id` is a nullable string (portable
across int/ulid host ids) with **no foreign key** — logs deliberately survive user deletion,
which is why `user_name` is snapshotted.

### Migration — `database/migrations/2026_06_09_000000_create_mail_logs_table.php`

Creates the table named `config('mailog.table', 'mail_logs')` with the columns above
(`ulid('id')->primary()`, json columns nullable, `longText` bodies, `string` ids/subject,
`timestamp` `sent_at` nullable, standard timestamps). Index on `status` and `created_at` for
audit querying. Loaded via `loadMigrationsFrom` and published under tag `mailog-migrations`.

### Listener — `src/Listeners/MailLogListener.php`

Bound as a **singleton** so a single instance handles both events in a request and holds the
in-memory `spl_object_id ⇒ MailLog id` correlation map. Both handler bodies are wrapped in
`try/catch (\Throwable)` that `report()`s and swallows — a logging failure must never break the
host's email send.

`sending(MessageSending $event)`:
1. Return if `! config('mailog.enabled')`.
2. If the message has the `X-Mailog-Skip` header → remove the header (so it is never
   transmitted) and return.
3. Return if `$event->data['mailer'] ?? null` is in `config('mailog.except.mailers')`.
4. Return if `$event->data['__laravel_mailable'] ?? null` is in `config('mailog.except.mailables')`.
5. Build a `MailLog` (`status = PENDING`) from `$event->message` + `$event->data`: extracted
   fields + resolved user, then `fill()` the data resolver's array on top (so host custom
   columns are populated); save; record `spl_object_id($event->message) => $log->id`.

`sent(MessageSent $event)`:
1. Look up the id via `spl_object_id($event->sent->getOriginalMessage())`.
2. If found → update `status = SENT`, `message_id = $event->sent->getMessageId()`,
   `sent_at = now()`, and drop the map entry.

Address extraction maps each `Symfony\Component\Mime\Address` to `['address' => getAddress(),
'name' => getName() ?: null]`. Attachment extraction maps each part to
`['filename' => getFilename(), 'mime' => getContentType(), 'size' => strlen(getBody())]`.

### User resolver — on the `Mailog` singleton

- `Mailog::resolveUserUsing(Closure $resolver): void` — static setter; host calls it in a
  service provider (cache-safe, unlike a config closure).
- Default resolver: `fn () => auth()->user()`.
- The listener calls the resolver and snapshots:
  - resolver returns a model → `user_id = $user->getKey()`, `user_name = $user->name ?? null`;
  - resolver returns `['id' => ..., 'name' => ...]` → used as-is (lets hosts whose user name is
    not in a `name` attribute supply it);
  - resolver returns `null` (guest / queue / console) → both null.

### Data resolver — host custom columns

- `Mailog::resolveDataUsing(Closure $resolver): void` — static setter; host calls it in a
  service provider. Receives the `MessageSending` event, returns an associative array of
  attributes (default `fn () => []`).
- The listener `fill()`s the returned array onto the (unguarded) `MailLog`. Keys must match real
  columns the host added via the published migration — e.g. a multi-tenant app publishes the
  migration, adds `$table->ulid('tenant_id')->nullable()->index();`, then
  `Mailog::resolveDataUsing(fn () => ['tenant_id' => tenant()->id])` and queries
  `MailLog::where('tenant_id', $id)`. There is no json catch-all; unknown keys would fail on
  insert (and are caught by the listener's try/catch), so hosts add the column first.

### `Mailog` singleton additions — `src/Mailog.php`

- `const SKIP_HEADER = 'X-Mailog-Skip';`
- static `resolveUserUsing()` / `resolveDataUsing()` setters, their default/stored resolvers,
  and internal `resolveUser()` / `resolveData()` used by the listener.
- Keep existing `version()` / `config()`.

### Service provider — `src/MailogServiceProvider.php`

- `register()`: existing config merge + `Mailog` singleton/alias; bind `MailLogListener` as a
  singleton.
- `boot()`:
  - `loadMigrationsFrom(__DIR__.'/../database/migrations')`;
  - register listeners: `Event::listen(MessageSending::class, [MailLogListener::class, 'sending'])`
    and `Event::listen(MessageSent::class, [MailLogListener::class, 'sent'])`;
  - in console: publish config (existing `mailog-config` tag) + migrations (`mailog-migrations`).

### Consumer guidelines — `resources/boost/guidelines/core.blade.php`

Replace the placeholder content with real usage guidance for host apps: logging is automatic,
how to exclude (config `except`, `X-Mailog-Skip` header), how to set the user resolver, how to
add custom columns (publish migration + `resolveDataUsing`), and how to query `MailLog`.

## Tests (Pest 4 + Testbench, in-memory sqlite)

`tests/TestCase.php` gains Testbench's `RefreshDatabase` so package migrations run. The default
mailer is set to `array` in the test environment so both mail events fire without a real
transport. Feature tests in `tests/Feature/MailLogTest.php` cover:

- a sent email creates one `MailLog` and captures from/to/cc/bcc/reply-to/subject/both bodies;
- attachment metadata is recorded (filename, mime, size);
- tags and metadata headers are captured;
- the row transitions `PENDING` → `SENT` with a `message_id` and `sent_at`;
- `config('mailog.except.mailers')` skips by mailer name;
- `config('mailog.except.mailables')` skips by Mailable class;
- the `X-Mailog-Skip` header skips the email **and** is stripped from the sent message;
- `config('mailog.enabled') = false` logs nothing;
- the user resolver populates `user_id` / `user_name`, and snapshots survive a deleted user;
- the data resolver fills a host-added column (the test schema adds a `tenant_id` column and
  asserts `resolveDataUsing(fn () => ['tenant_id' => …])` persists it);
- a throwing resolver is swallowed — the email still sends (logging never breaks the pipeline);
- (Unit) the `Status` enum behaves via the `Enum` trait.

A test Mailable and a test user model live under `workbench/` (or are defined inline in the
test) to exercise the Mailable/tag/metadata and resolver paths.

## Out of scope (YAGNI)

- Storing attachment bytes / a retrievable file path (metadata only).
- Automatic `FAILED` status transition, retries, or bounce/delivery webhook ingestion (the
  `FAILED` case exists for hosts to set; ingestion is a later feature).
- A UI, routes, or controllers for browsing logs.
- Runtime `withoutLogging()` scope, body size caps, and pruning/retention commands.
- Recipient-based user lookup (resolver is actor/auth-based).

## Success criteria

- `composer test` is green, covering every behaviour listed above.
- A host app that installs the package and runs `php artisan migrate` logs all outgoing email
  with no further code, and can opt out via config, the `X-Mailog-Skip` header, or the master
  switch.
- `MailLog` rows correctly reflect sent vs pending (failed) outcomes and carry the resolved
  user with a name snapshot that outlives the user record.
