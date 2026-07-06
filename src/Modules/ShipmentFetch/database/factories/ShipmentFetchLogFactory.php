<?php

namespace Modules\ShipmentFetch\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;

/**
 * ShipmentFetchLog モデルのテスト用ファクトリ。
 *
 * @extends Factory<ShipmentFetchLog>
 */
class ShipmentFetchLogFactory extends Factory
{
    protected $model = ShipmentFetchLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ShipmentFetchLog::STATUS_RUNNING,
            'started_at' => now(),
            'completed_at' => null,
            'fetched_count' => 0,
            'created_delivery_note_count' => 0,
            'created_invoice_count' => 0,
            'skipped_count' => 0,
            'execution_seconds' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ShipmentFetchLog::STATUS_COMPLETED,
            'completed_at' => now(),
            'execution_seconds' => 2.0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ShipmentFetchLog::STATUS_FAILED,
            'error_message' => 'error',
        ]);
    }
}
