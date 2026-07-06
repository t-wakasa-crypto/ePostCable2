<?php

namespace Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Invoice\Database\Factories\InvoiceItemFactory;

/**
 * 請求書明細モデル（詳細設計 §4.2 / BR-09）。
 * 親 invoices への FK は cascade delete。
 */
class InvoiceItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
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
     * 親請求書（belongsTo）。
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }
}
