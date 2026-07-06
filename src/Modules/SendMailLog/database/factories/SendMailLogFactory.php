<?php

namespace Modules\SendMailLog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SendMailLog\Models\SendMailLog;

/**
 * SendMailLog モデルのテスト用ファクトリ。
 *
 * @extends Factory<SendMailLog>
 */
class SendMailLogFactory extends Factory
{
    protected $model = SendMailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_key' => SendMailLog::BATCH_SEND_INVOICES,
            'batch_name' => '請求書',
            'started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
            'dispatched_count' => 0,
            'reset_count' => 0,
            'retry_failed_count' => 0,
            'execution_seconds' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now(), 'execution_seconds' => 1.5]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['failed_at' => now()]);
    }

    public function manualResend(): static
    {
        return $this->state(fn () => [
            'batch_key' => SendMailLog::BATCH_MANUAL_RESEND,
            'batch_name' => '手動再送',
        ]);
    }
}
