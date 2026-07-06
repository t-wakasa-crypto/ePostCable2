<?php

namespace Modules\SendMailLog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\SendMailLog\Database\Factories\SendMailLogItemFactory;

/**
 * 書類1通単位の送信明細ログ（詳細設計 §4.4 / NFR-E-02 / NFR-M-03 / BR-09）。
 *
 * ポリモーフィック関連（sendable）で Invoice / DeliveryNote を参照する。
 * sendable_type には Relation::morphMap() の論理名（'invoice' / 'delivery_note'）を
 * 格納する（Q-15 決定）。親（send_mail_log_id）は restrictOnDelete で削除不可。
 */
class SendMailLogItem extends Model
{
    use HasFactory;

    /** 送信待ち */
    public const STATUS_PENDING = 'pending';

    /** 送信処理中 */
    public const STATUS_PROCESSING = 'processing';

    /** 送信成功 */
    public const STATUS_SENT = 'sent';

    /** 一時失敗 */
    public const STATUS_FAILED = 'failed';

    /** 恒久失敗 */
    public const STATUS_FAILED_PERMANENT = 'failed_permanent';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'send_mail_log_id',
        'sendable_type',
        'sendable_id',
        'status',
        'error_message',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * 送信対象書類（ポリモーフィック・NFR-E-02）。
     */
    public function sendable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 親ログ（belongsTo）。
     */
    public function sendMailLog(): BelongsTo
    {
        return $this->belongsTo(SendMailLog::class);
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): SendMailLogItemFactory
    {
        return SendMailLogItemFactory::new();
    }
}
