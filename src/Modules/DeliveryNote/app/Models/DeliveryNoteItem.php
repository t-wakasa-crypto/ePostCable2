<?php

namespace Modules\DeliveryNote\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\DeliveryNote\Database\Factories\DeliveryNoteItemFactory;

/**
 * 納品書明細モデル（詳細設計 §4.2 / BR-09）。
 * 親 delivery_notes への FK は cascade delete。
 */
class DeliveryNoteItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'delivery_note_id',
        'item_name',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:0',
            'amount' => 'decimal:0',
            'sort_order' => 'integer',
        ];
    }

    /**
     * 親納品書（belongsTo）。
     */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): DeliveryNoteItemFactory
    {
        return DeliveryNoteItemFactory::new();
    }
}
