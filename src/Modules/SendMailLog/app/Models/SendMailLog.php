<?php

namespace Modules\SendMailLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SendMailLog\Database\Factories\SendMailLogFactory;

/**
 * 送信バッチ実行の親ログ（詳細設計 §4.3 / FR-02 / FR-03 / BR-03 / BR-07）。
 *
 * 手動再送まとめ親（batch_key='manual-resend'）も同テーブルに格納し、当日1件に集約する。
 */
class SendMailLog extends Model
{
    use HasFactory;

    /** 請求書送信バッチ */
    public const BATCH_SEND_INVOICES = 'send-invoices';

    /** 納品書送信バッチ */
    public const BATCH_SEND_DELIVERY_NOTES = 'send-delivery-notes';

    /** 手動再送まとめ親 */
    public const BATCH_MANUAL_RESEND = 'manual-resend';

    /** displayStatus 表示状態 */
    public const DISPLAY_FAILED = 'failed';

    public const DISPLAY_COMPLETED = 'completed';

    public const DISPLAY_RUNNING = 'running';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'batch_key',
        'batch_name',
        'started_at',
        'completed_at',
        'failed_at',
        'dispatched_count',
        'reset_count',
        'retry_failed_count',
        'execution_seconds',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'dispatched_count' => 'integer',
            'reset_count' => 'integer',
            'retry_failed_count' => 'integer',
            'execution_seconds' => 'float',
        ];
    }

    /**
     * 送信明細（hasMany）。
     */
    public function items(): HasMany
    {
        return $this->hasMany(SendMailLogItem::class);
    }

    /**
     * 表示状態を判定する（BR-03）。
     *
     * failed_at が最優先。completed_at があれば completed、両方 null なら running。
     * batch_key='manual-resend' は状態判定・集計の対象外（呼び出し側で除外）。
     */
    public function displayStatus(): string
    {
        if ($this->failed_at !== null) {
            return self::DISPLAY_FAILED;
        }

        if ($this->completed_at !== null) {
            return self::DISPLAY_COMPLETED;
        }

        return self::DISPLAY_RUNNING;
    }

    /**
     * 手動再送まとめ親（当日分）を取得または作成する（BR-07）。
     *
     * 当日の batch_key='manual-resend' レコードが存在すればそれを、なければ新規作成する。
     */
    public static function manualResendBucket(): self
    {
        return self::firstOrCreate(
            [
                'batch_key' => self::BATCH_MANUAL_RESEND,
                'started_at' => now()->startOfDay(),
            ],
            [
                'batch_name' => '手動再送',
                'dispatched_count' => 0,
            ]
        );
    }

    /**
     * 手動再送まとめ親を集計・一覧から除外するスコープ（BR-03 / BR-07）。
     */
    public function scopeExcludeManualResend(Builder $query): Builder
    {
        return $query->where('batch_key', '!=', self::BATCH_MANUAL_RESEND);
    }

    /**
     * index の filter allowlist に対応するフィルタスコープ（FR-10）。
     */
    public function scopeFilter(Builder $query, ?string $filter): Builder
    {
        switch ($filter) {
            case self::DISPLAY_COMPLETED:
                return $query->excludeManualResend()->whereNull('failed_at')->whereNotNull('completed_at');
            case self::DISPLAY_RUNNING:
                return $query->excludeManualResend()->whereNull('failed_at')->whereNull('completed_at');
            case self::DISPLAY_FAILED:
                return $query->excludeManualResend()->whereNotNull('failed_at');
            case 'manual_resend':
                return $query->where('batch_key', self::BATCH_MANUAL_RESEND);
            case 'has_pending':
                return $query->whereHas('items', fn ($q) => $q->where('status', SendMailLogItem::STATUS_PENDING));
            case 'has_sent':
                return $query->whereHas('items', fn ($q) => $q->where('status', SendMailLogItem::STATUS_SENT));
            case 'has_failure':
                return $query->whereHas('items', fn ($q) => $q->where('status', SendMailLogItem::STATUS_FAILED));
            case 'has_failure_permanent':
                return $query->whereHas('items', fn ($q) => $q->where('status', SendMailLogItem::STATUS_FAILED_PERMANENT));
            default:
                return $query;
        }
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): SendMailLogFactory
    {
        return SendMailLogFactory::new();
    }
}
