<?php

namespace Modules\DeliveryNote\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\DeliveryNote\Models\DeliveryNoteItem;

/**
 * DeliveryNoteItem モデルのテスト用ファクトリ。
 *
 * @extends Factory<DeliveryNoteItem>
 */
class DeliveryNoteItemFactory extends Factory
{
    protected $model = DeliveryNoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->numberBetween(100, 10000);

        return [
            'delivery_note_id' => DeliveryNote::factory(),
            'item_name' => fake()->word(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $quantity * $unitPrice,
            'sort_order' => null,
        ];
    }
}
