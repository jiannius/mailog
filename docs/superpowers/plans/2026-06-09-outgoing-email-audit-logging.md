# Outgoing Email Audit Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `jiannius/mailog` log every outgoing email a host Laravel app sends into a `mail_logs` table for audit tracking, on by default, with config/header/master-switch opt-outs and host-overridable user + custom-column resolvers.

**Architecture:** A singleton `MailLogListener` subscribes to Laravel's `MessageSending` + `MessageSent` events. On *sending* it creates a `PENDING` `MailLog` from the Symfony `Email` (and remembers `spl_object_id => id`); on *sent* it flips the row to `SENT` with the transport message id. A row left `PENDING` means the send threw (failure visibility). Capture is wrapped in try/catch so logging never breaks the host's mail. Host context is attached via static resolvers on the `Mailog` singleton.

**Tech Stack:** PHP 8.4, Laravel framework v13 (mail events), Symfony Mime/Mailer, Orchestra Testbench v11, Pest 4, in-memory sqlite.

**Spec:** `docs/superpowers/specs/2026-06-09-outgoing-email-audit-logging-design.md`

---

## File Structure

**Create:**
- `src/Enums/Status.php` — `PENDING`/`SENT`/`FAILED` backed enum (uses the `Enum` trait).
- `database/migrations/2026_06_09_000000_create_mail_logs_table.php` — the table.
- `src/Models/MailLog.php` — unguarded ULID model, casts, `pending()`/`sent()` scopes, `newFactory()`.
- `database/factories/MailLogFactory.php` — factory (+ `sent()` state).
- `src/Listeners/MailLogListener.php` — the capture logic.
- `tests/Unit/StatusTest.php` — enum behaviour.
- `tests/Unit/ResolverTest.php` — `Mailog::resolveUser()`/`resolveData()` unit behaviour.
- `tests/Feature/MailLogTest.php` — end-to-end capture/exclusion/resolver tests.

**Modify:**
- `src/Mailog.php` — add `SKIP_HEADER`, static `resolveUserUsing()`/`resolveDataUsing()`, instance `resolveUser()`/`resolveData()`.
- `src/MailogServiceProvider.php` — bind listener singleton; in `boot()` load migrations, register the two event listeners, publish migrations.
- `config/mailog.php` — replace sample config with `enabled`/`table`/`except`.
- `composer.json` — map the factory namespace.
- `tests/TestCase.php` — set the `array` mailer + a default `mail.from` for the test app.
- `resources/boost/guidelines/core.blade.php` — rewrite as real usage guidance.

---

## Setup (run once, before Task 1)

This worktree has no `vendor/` yet. Install dependencies so tests can run.

- [ ] **Step 1: Install dependencies in the worktree**

Run: `composer install --no-interaction`
Expected: completes; `vendor/bin/pest` exists.

- [ ] **Step 2: Confirm the existing suite is green**

Run: `vendor/bin/pest`
Expected: PASS (scaffold tests: Smoke, ServiceProvider, ConsumerGuidelines, Enum).

---

## Task 1: Status enum

**Files:**
- Create: `src/Enums/Status.php`
- Test: `tests/Unit/StatusTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/StatusTest.php`:

```php
<?php

use Jiannius\Mailog\Enums\Status;

it('exposes the mail-log statuses via the Enum trait', function () {
    expect(Status::all()->pluck('value')->all())->toBe(['pending', 'sent', 'failed']);
    expect(Status::PENDING->label())->toBe('Pending');
    expect(Status::get('sent'))->toBe(Status::SENT);
    expect(Status::SENT->is('sent'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/StatusTest.php`
Expected: FAIL — `Class "Jiannius\Mailog\Enums\Status" not found`.

- [ ] **Step 3: Write the enum**

Create `src/Enums/Status.php`:

```php
<?php

namespace Jiannius\Mailog\Enums;

use Jiannius\Mailog\Traits\Enum;

enum Status: string
{
    use Enum;

    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/StatusTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Enums/Status.php tests/Unit/StatusTest.php
git commit -m "feat: add mail-log Status enum"
```

---

## Task 2: Migration, MailLog model, factory

**Files:**
- Create: `database/migrations/2026_06_09_000000_create_mail_logs_table.php`
- Create: `src/Models/MailLog.php`
- Create: `database/factories/MailLogFactory.php`
- Modify: `composer.json` (autoload map for the factory namespace)
- Test: `tests/Feature/MailLogModelTest.php`

- [ ] **Step 1: Replace the sample config**

Overwrite `config/mailog.php` with:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail logging
    |--------------------------------------------------------------------------
    |
    | Outgoing emails are logged to the database by default. Set "enabled" to
    | false (or MAILOG_ENABLED=false) to switch all logging off. "except" lists
    | mailer names and/or Mailable classes that should never be logged.
    |
    */

    'enabled' => env('MAILOG_ENABLED', true),

    'table' => 'mail_logs',

    'except' => [
        'mailers' => [],
        'mailables' => [],
    ],

];
```

- [ ] **Step 2: Map the factory namespace in composer.json**

In `composer.json`, change the `autoload.psr-4` block from:

```json
        "psr-4": {
            "Jiannius\\Mailog\\": "src/"
        },
```

to:

```json
        "psr-4": {
            "Jiannius\\Mailog\\": "src/",
            "Jiannius\\Mailog\\Database\\Factories\\": "database/factories/"
        },
```

Then run: `composer dump-autoload`
Expected: "Generated autoload files".

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_06_09_000000_create_mail_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status')->default('pending')->index();
            $table->string('mailer')->nullable();
            $table->string('mailable')->nullable();
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->string('subject')->nullable();
            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();
            $table->json('attachments')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    protected function table(): string
    {
        return config('mailog.table', 'mail_logs');
    }
};
```

- [ ] **Step 4: Write the model**

Create `src/Models/MailLog.php`:

```php
<?php

namespace Jiannius\Mailog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jiannius\Mailog\Database\Factories\MailLogFactory;
use Jiannius\Mailog\Enums\Status;

class MailLog extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Unguarded so host apps can add their own columns (e.g. tenant_id) and
     * have the data resolver mass-assign them. See Mailog::resolveDataUsing().
     */
    protected $guarded = [];

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'from' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'attachments' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Resolve the table name from config.
     */
    public function getTable(): string
    {
        return config('mailog.table', 'mail_logs');
    }

    /**
     * Scope to logs still pending (in-flight or failed).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', Status::PENDING->value);
    }

    /**
     * Scope to logs confirmed sent.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', Status::SENT->value);
    }

    /**
     * The package factory for this model.
     */
    protected static function newFactory(): MailLogFactory
    {
        return MailLogFactory::new();
    }
}
```

- [ ] **Step 5: Write the factory**

Create `database/factories/MailLogFactory.php`:

```php
<?php

namespace Jiannius\Mailog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Models\MailLog;

class MailLogFactory extends Factory
{
    protected $model = MailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => Status::PENDING,
            'mailer' => 'array',
            'from' => [['address' => fake()->safeEmail(), 'name' => fake()->name()]],
            'to' => [['address' => fake()->safeEmail(), 'name' => fake()->name()]],
            'subject' => fake()->sentence(),
            'html_body' => '<p>'.fake()->sentence().'</p>',
        ];
    }

    /**
     * Mark the log as sent.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => Status::SENT,
            'message_id' => '<'.fake()->uuid().'@example.com>',
            'sent_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Write the test**

Create `tests/Feature/MailLogModelTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Models\MailLog;

uses(RefreshDatabase::class);

it('persists a mail log via the factory with casts and a ulid key', function () {
    $log = MailLog::factory()->create();

    expect($log->getKey())->toBeString()->toHaveLength(26);
    expect($log->status)->toBe(Status::PENDING);
    expect($log->from)->toBeArray();
    expect(MailLog::pending()->count())->toBe(1);
});

it('exposes the sent factory state and scope', function () {
    MailLog::factory()->sent()->create();

    $log = MailLog::sent()->sole();
    expect($log->status)->toBe(Status::SENT);
    expect($log->sent_at)->not->toBeNull();
});
```

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/MailLogModelTest.php`
Expected: PASS (migration runs via the provider + RefreshDatabase).

- [ ] **Step 8: Commit**

```bash
git add config/mailog.php composer.json database/migrations database/factories src/Models tests/Feature/MailLogModelTest.php
git commit -m "feat: add mail_logs migration, MailLog model and factory"
```

---

## Task 3: Mailog singleton — skip header + resolvers

**Files:**
- Modify: `src/Mailog.php`
- Test: `tests/Unit/ResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ResolverTest.php`:

```php
<?php

use Illuminate\Mail\Events\MessageSending;
use Jiannius\Mailog\Mailog;
use Symfony\Component\Mime\Email;

afterEach(function () {
    Mailog::resolveUserUsing(null);
    Mailog::resolveDataUsing(null);
});

function fakeSendingEvent(): MessageSending
{
    return new MessageSending(new Email, ['mailer' => 'array']);
}

it('resolves a user model to a getKey + name snapshot', function () {
    $user = new class
    {
        public string $name = 'Jane Doe';

        public function getKey(): int
        {
            return 42;
        }
    };

    Mailog::resolveUserUsing(fn () => $user);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))
        ->toBe(['user_id' => 42, 'user_name' => 'Jane Doe']);
});

it('resolves an explicit array user shape as-is', function () {
    Mailog::resolveUserUsing(fn () => ['id' => 'u-1', 'name' => 'Bob']);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))
        ->toBe(['user_id' => 'u-1', 'user_name' => 'Bob']);
});

it('returns no user attributes when the resolver yields null', function () {
    Mailog::resolveUserUsing(fn () => null);

    expect(app(Mailog::class)->resolveUser(fakeSendingEvent()))->toBe([]);
});

it('resolves host data and defaults to an empty array', function () {
    Mailog::resolveDataUsing(fn () => ['tenant_id' => 'acme']);
    expect(app(Mailog::class)->resolveData(fakeSendingEvent()))->toBe(['tenant_id' => 'acme']);

    Mailog::resolveDataUsing(null);
    expect(app(Mailog::class)->resolveData(fakeSendingEvent()))->toBe([]);
});

it('exposes the skip header constant', function () {
    expect(Mailog::SKIP_HEADER)->toBe('X-Mailog-Skip');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ResolverTest.php`
Expected: FAIL — `Call to undefined method ...::resolveUserUsing()` / undefined constant.

- [ ] **Step 3: Add the constant + resolvers to the singleton**

Overwrite `src/Mailog.php`:

```php
<?php

namespace Jiannius\Mailog;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Mail\Events\MessageSending;

class Mailog
{
    /**
     * Header a host sets on a message to opt it out of logging.
     */
    public const SKIP_HEADER = 'X-Mailog-Skip';

    /**
     * Host-set resolver for the user to attribute a log to.
     */
    protected static ?Closure $userResolver = null;

    /**
     * Host-set resolver for extra columns to fill on a log.
     */
    protected static ?Closure $dataResolver = null;

    /**
     * Set (or, with null, reset) the user resolver. Called by the host app in
     * a service provider — cache-safe, unlike a closure in the config file.
     */
    public static function resolveUserUsing(?Closure $resolver): void
    {
        static::$userResolver = $resolver;
    }

    /**
     * Set (or, with null, reset) the data resolver for host custom columns.
     */
    public static function resolveDataUsing(?Closure $resolver): void
    {
        static::$dataResolver = $resolver;
    }

    /**
     * Resolve the user attributes to store on a sending message's log.
     *
     * @return array{user_id?: mixed, user_name?: ?string}
     */
    public function resolveUser(MessageSending $event): array
    {
        $resolver = static::$userResolver ?? fn (): mixed => auth()->user();

        $user = $resolver($event);

        if ($user === null) {
            return [];
        }

        if (is_array($user)) {
            return [
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
            ];
        }

        return [
            'user_id' => $user->getKey(),
            'user_name' => $user->name ?? null,
        ];
    }

    /**
     * Resolve host-supplied custom attributes for a sending message's log.
     *
     * @return array<string, mixed>
     */
    public function resolveData(MessageSending $event): array
    {
        $resolver = static::$dataResolver ?? fn (): array => [];

        return $resolver($event) ?: [];
    }

    /**
     * The package version.
     */
    public function version(): string
    {
        return InstalledVersions::getPrettyVersion('jiannius/mailog') ?? 'dev';
    }

    /**
     * Read a package config value (dot notation, scoped to "mailog").
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return config($key ? "mailog.{$key}" : 'mailog', $default);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ResolverTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Mailog.php tests/Unit/ResolverTest.php
git commit -m "feat: add skip header constant and user/data resolvers to Mailog"
```

---

## Task 4: MailLogListener + service-provider wiring

This builds the core capture (no try/catch yet — that is Task 6) and wires it up.

**Files:**
- Create: `src/Listeners/MailLogListener.php`
- Modify: `src/MailogServiceProvider.php`
- Modify: `tests/TestCase.php`
- Test: `tests/Feature/MailLogTest.php`

- [ ] **Step 1: Configure the test mail environment**

In `tests/TestCase.php`, add to the end of `defineEnvironment()`:

```php
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('mail.from', ['address' => 'noreply@example.com', 'name' => 'Test']);
```

- [ ] **Step 2: Write the failing driving test**

Create `tests/Feature/MailLogTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Jiannius\Mailog\Enums\Status;
use Jiannius\Mailog\Mailog;
use Jiannius\Mailog\Models\MailLog;

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
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php`
Expected: FAIL — `Jiannius\Mailog\Models\MailLog::sole(): no rows` (nothing is logging yet).

- [ ] **Step 4: Write the listener**

Create `src/Listeners/MailLogListener.php`:

```php
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
     * Map of spl_object_id($message) => MailLog id, bridging sending → sent.
     *
     * @var array<int, string>
     */
    protected array $pending = [];

    /**
     * Create a pending log for an outgoing message.
     */
    public function sending(MessageSending $event): void
    {
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

        $this->pending[spl_object_id($message)] = $log->getKey();
    }

    /**
     * Flip the pending log to sent once the transport accepts it.
     */
    public function sent(MessageSent $event): void
    {
        $key = spl_object_id($event->sent->getOriginalMessage());

        $id = $this->pending[$key] ?? null;

        if ($id === null) {
            return;
        }

        unset($this->pending[$key]);

        MailLog::query()->whereKey($id)->update([
            'status' => Status::SENT->value,
            'message_id' => $event->sent->getMessageId(),
            'sent_at' => now(),
        ]);
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
```

- [ ] **Step 5: Wire the listener into the service provider**

In `src/MailogServiceProvider.php`, add these imports below the existing `use` line:

```php
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Jiannius\Mailog\Listeners\MailLogListener;
```

In `register()`, after the `alias(...)` line, add:

```php
        // Singleton so one instance bridges MessageSending → MessageSent via its map.
        $this->app->singleton(MailLogListener::class);
```

Replace the body of `boot()` with:

```php
        // Migrations — host apps pick them up with `php artisan migrate`.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Capture every outgoing email (logging is on by default).
        Event::listen(MessageSending::class, [MailLogListener::class, 'sending']);
        Event::listen(MessageSent::class, [MailLogListener::class, 'sent']);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mailog.php' => config_path('mailog.php'),
            ], 'mailog-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'mailog-migrations');
        }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Listeners src/MailogServiceProvider.php tests/TestCase.php tests/Feature/MailLogTest.php
git commit -m "feat: capture outgoing email into MailLog via mail events"
```

---

## Task 5: Field coverage — text body, attachments, tags, metadata

Extraction is already implemented in Task 4; these tests guard each field path.

**Files:**
- Modify: `tests/Feature/MailLogTest.php`

- [ ] **Step 1: Add the tests**

Append to `tests/Feature/MailLogTest.php`:

```php
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
```

Add these imports at the top of the file (below the existing `use` lines):

```php
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php`
Expected: PASS (all field tests green).

> If `size` mismatches, it means `DataPart::getBody()` returned an encoded body; change the assertion to `expect($attachments[0]['size'])->toBeGreaterThan(0)` and note it — but the decoded body is expected.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/MailLogTest.php
git commit -m "test: cover text body, attachments, tags and metadata capture"
```

---

## Task 6: Opt-out behaviours

Drives the master switch, config excludes, and the skip header. The guard clauses already exist in the listener from Task 4, so these tests confirm them (and the Mailable test confirms `__laravel_mailable` capture). The skip-header strip is the one genuinely new assertion.

**Files:**
- Modify: `tests/Feature/MailLogTest.php`

- [ ] **Step 1: Add a test Mailable + opt-out tests**

Append to `tests/Feature/MailLogTest.php`:

```php
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
```

Add the test Mailable class at the very bottom of the file:

```php
class MailogTestMailable extends \Illuminate\Mail\Mailable
{
    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(subject: 'From Mailable');
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(htmlString: '<p>From mailable</p>');
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/MailLogTest.php
git commit -m "test: cover master switch, config excludes and skip header"
```

---

## Task 7: Robustness — capture must never break the send

The throwing-resolver test fails first because, without try/catch, a listener exception propagates out of `Mail::send`. Wrapping the handlers fixes it. The custom-column test confirms the data resolver fills a host-added column.

**Files:**
- Modify: `src/Listeners/MailLogListener.php`
- Modify: `tests/Feature/MailLogTest.php`

- [ ] **Step 1: Add the failing robustness test + the custom-column test**

Append to `tests/Feature/MailLogTest.php`:

```php
it('fills host custom columns via the data resolver', function () {
    Schema::table('mail_logs', fn ($table) => $table->string('tenant_id')->nullable());

    Mailog::resolveDataUsing(fn () => ['tenant_id' => 'acme']);

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('D'));

    expect(MailLog::sole()->tenant_id)->toBe('acme');
});

it('never breaks the email send when capture throws', function () {
    Mailog::resolveDataUsing(fn () => throw new RuntimeException('boom'));

    Mail::html('<p>x</p>', fn ($m) => $m->from('s@example.com')->to('t@example.com')->subject('Boom'));

    expect(MailLog::count())->toBe(0);
})->throwsNoExceptions();
```

Add this import at the top of the file:

```php
use Illuminate\Support\Facades\Schema;
```

- [ ] **Step 2: Run to verify the robustness test fails**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php --filter='never breaks'`
Expected: FAIL — the `RuntimeException('boom')` propagates out of `Mail::html`.

- [ ] **Step 3: Wrap both handlers in try/catch**

In `src/Listeners/MailLogListener.php`, wrap the **entire body** of `sending()` in:

```php
        try {
            // ... existing sending() body ...
        } catch (\Throwable $e) {
            report($e);
        }
```

and the **entire body** of `sent()` in the same `try { ... } catch (\Throwable $e) { report($e); }`.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/pest tests/Feature/MailLogTest.php`
Expected: PASS (both new tests + all prior tests green).

- [ ] **Step 5: Commit**

```bash
git add src/Listeners/MailLogListener.php tests/Feature/MailLogTest.php
git commit -m "feat: swallow capture failures so logging never breaks the send"
```

---

## Task 8: Consumer-facing Boost guidelines

**Files:**
- Modify: `resources/boost/guidelines/core.blade.php`

- [ ] **Step 1: Rewrite the guideline**

Overwrite `resources/boost/guidelines/core.blade.php`:

```blade
## Mailog

`jiannius/mailog` logs every outgoing email this app sends into the `mail_logs` table for audit
tracking. Its service provider auto-registers and listens to Laravel's mail events — logging is
**on by default with no code changes**. Run `php artisan migrate` after installing.

### What is logged

Each send creates a `Jiannius\Mailog\Models\MailLog` row: sender, recipients (to/cc/bcc/reply-to),
subject, both HTML and text bodies, attachment metadata (filename/mime/size), tags, metadata,
the mailer + Mailable class, the resolved user, and the outcome. A row is created `PENDING` on
send and flipped to `SENT` once the transport accepts it — a row left `PENDING` means the send
failed.

@verbatim
<code-snippet name="Querying logs" lang="php">
use Jiannius\Mailog\Models\MailLog;

MailLog::sent()->latest()->get();
MailLog::pending()->get();          // in-flight or failed
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
```

- [ ] **Step 2: Verify the guideline still renders + contains the required tokens**

Run: `vendor/bin/pest tests/Feature/ConsumerGuidelinesTest.php`
Expected: PASS (rendered output contains `Mailog` and `mailog()`).

- [ ] **Step 3: Commit**

```bash
git add resources/boost/guidelines/core.blade.php
git commit -m "docs: rewrite consumer Boost guideline for mail logging"
```

---

## Task 9: Full suite, lint, finish

**Files:** none (verification only).

- [ ] **Step 1: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS — all suites green (Smoke, ServiceProvider, ConsumerGuidelines, Enum, Status, Resolver, MailLogModel, MailLog).

- [ ] **Step 2: Lint**

Run: `vendor/bin/pint`
Expected: fixes applied / no issues.

- [ ] **Step 3: Re-run the suite after lint**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 4: Commit any lint changes**

```bash
git add -A
git commit -m "style: pint" || echo "nothing to lint-commit"
```

- [ ] **Step 5: Finish**

Use `superpowers:finishing-a-development-branch` to squash-merge into `main`, then remove the worktree (per project workflow: squash-merge, then `ExitWorktree` with `action: "remove"`).

---

## Self-Review

**Spec coverage:**
- Capture via MessageSending/MessageSent → Task 4. ✔
- Master switch / except.mailers / except.mailables / skip header (stripped) → Task 6. ✔
- Sent + failed (pending→sent, lingering pending) → Tasks 4 (transition) + model scopes (Task 2). ✔
- Both bodies full → Task 4 (driving) + Task 5 (text). ✔
- Attachment metadata only → Task 5. ✔
- Tags + metadata → Task 5. ✔
- mailer + mailable columns → Task 4 (mailer) + Task 6 (mailable). ✔
- User resolver (default auth, object/array/null, snapshot) → Task 3 (unit) + Task 4 (end-to-end). ✔
- Host custom columns: unguarded model + publishable migration + resolveDataUsing → Tasks 2 + 7. ✔
- Robustness try/catch → Task 7. ✔
- Migration auto-load + publishable → Task 4 (boot wiring). ✔
- Status enum → Task 1. ✔
- Consumer guidelines → Task 8. ✔
- Tests for every behaviour → Tasks 1–7. ✔

**Type/name consistency:** `Status` (PENDING/SENT/FAILED), `MailLog`, `MailLogListener::sending/sent`, `Mailog::SKIP_HEADER`/`resolveUserUsing`/`resolveDataUsing`/`resolveUser`/`resolveData`, factory `MailLogFactory` (namespace `Jiannius\Mailog\Database\Factories`, mapped in composer.json) — consistent across tasks.

**Placeholder scan:** None. Every code/test step contains complete content; every `@verbatim` block in Task 8 is closed with `@endverbatim`.
