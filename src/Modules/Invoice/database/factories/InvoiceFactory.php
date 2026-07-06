<?php

namespace Modules\Invoice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoice\Models\Invoice;

/**
 * Invoice モデルのテスト用ファクトリ。
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 1000000);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'customer_email_2' => null,
            'customer_email_3' => null,
            'amount' => $amount,
            'tax' => 10,
            'tax_amount' => (int) round($amount * 10 / 100),
            'status' => Invoice::STATUS_PENDING,
            'retry_count' => 0,
            'sent_at' => null,
            'issue_date' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_PENDING]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_PROCESSING]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_SENT, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_FAILED]);
    }

    public function failedPermanent(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_FAILED_PERMANENT]);
    }
}
