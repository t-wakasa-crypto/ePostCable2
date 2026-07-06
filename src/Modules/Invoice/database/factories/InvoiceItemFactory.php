<?php

namespace Modules\Invoice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceItem;

/**
 * InvoiceItem モデルのテスト用ファクトリ。
 *
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->numberBetween(100, 10000);

        return [
            'invoice_id' => Invoice::factory(),
            'item_name' => fake()->word(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $quantity * $unitPrice,
            'sort_order' => null,
        ];
    }
}
