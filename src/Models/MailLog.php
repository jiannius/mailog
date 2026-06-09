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
