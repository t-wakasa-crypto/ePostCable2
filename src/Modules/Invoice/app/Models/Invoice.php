<?php

namespace Modules\Invoice\Models;

use App\Support\Concerns\HasDocumentStatuses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Invoice\Database\Factories\InvoiceFactory;
use Modules\SendMailLog\Models\SendMailLogItem;

/**
 * 請求書モデル（詳細設計 §4.1 / FR-01 / BR-01 / BR-02 / BR-04 / NFR-E-02）。
 *
 * ステータス定数・スコープは HasDocumentStatuses トレイトで共通化する。
 * 送付先（customer_email 系・最大3件）は recipientEmails() で trim・空除去して返す。
 */
class Invoice extends Model
{
    use HasDocumentStatuses, HasFactory;

    /** morphMap の論理名（Q-15 決定・実クラスのフル修飾名ではない） */
    public const MORPH_ALIAS = 'invoice';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_name',
        'customer_email',
        'customer_email_2',
        'customer_email_3',
        'amount',
        'tax',
        'tax_amount',
        'status',
        'retry_count',
        'sent_at',
        'issue_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'tax' => 'decimal:0',
            'tax_amount' => 'decimal:0',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'issue_date' => 'datetime',
        ];
    }

    /**
     * 送付先メールアドレス配列を返す（BR-04）。
     *
     * customer_email 系（最大3件）を trim し、空文字を除去した配列で返す。
     *
     * @return array<int, string>
     */
    public function recipientEmails(): array
    {
        return collect([
            $this->customer_email,
            $this->customer_email_2,
            $this->customer_email_3,
        ])
            ->map(fn ($email) => is_string($email) ? trim($email) : '')
            ->filter(fn (string $email) => $email !== '')
            ->values()
            ->all();
    }

    /**
     * 請求書明細（hasMany・親 FK cascade delete・BR-09）。
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * 送信明細ログ（ポリモーフィック・NFR-E-02）。
     */
    public function sendMailLogItems(): MorphMany
    {
        return $this->morphMany(SendMailLogItem::class, 'sendable');
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
