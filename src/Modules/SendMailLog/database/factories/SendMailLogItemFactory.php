<?php

namespace Modules\SendMailLog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;

/**
 * SendMailLogItem モデルのテスト用ファクトリ。
 *
 * @extends Factory<SendMailLogItem>
 */
class SendMailLogItemFactory extends Factory
{
    protected $model = SendMailLogItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'send_mail_log_id' => SendMailLog::factory(),
            'sendable_type' => Invoice::MORPH_ALIAS,
            'sendable_id' => Invoice::factory(),
            'status' => SendMailLogItem::STATUS_PENDING,
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => SendMailLogItem::STATUS_SENT, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => SendMailLogItem::STATUS_FAILED, 'error_message' => 'error']);
    }
}
