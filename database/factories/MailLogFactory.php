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
