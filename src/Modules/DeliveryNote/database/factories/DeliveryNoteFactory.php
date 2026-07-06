<?php

namespace Modules\DeliveryNote\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\DeliveryNote\Models\DeliveryNote;

/**
 * DeliveryNote モデルのテスト用ファクトリ。
 *
 * @extends Factory<DeliveryNote>
 */
class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 1000000);

        return [
            'delivery_number' => 'DN-'.fake()->unique()->numerify('########'),
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'customer_email_2' => null,
            'customer_email_3' => null,
            'amount' => $amount,
            'tax' => 10,
            'tax_amount' => (int) round($amount * 10 / 100),
            'status' => DeliveryNote::STATUS_PENDING,
            'retry_count' => 0,
            'sent_at' => null,
            'delivery_date' => now(),
            'issue_date' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => DeliveryNote::STATUS_PENDING]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => DeliveryNote::STATUS_PROCESSING]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => DeliveryNote::STATUS_SENT, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => DeliveryNote::STATUS_FAILED]);
    }

    public function failedPermanent(): static
    {
        return $this->state(fn () => ['status' => DeliveryNote::STATUS_FAILED_PERMANENT]);
    }
}
