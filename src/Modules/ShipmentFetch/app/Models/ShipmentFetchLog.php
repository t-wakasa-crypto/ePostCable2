<?php

namespace Modules\ShipmentFetch\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\ShipmentFetch\Database\Factories\ShipmentFetchLogFactory;

/**
 * 出荷取得バッチの実行ログ（詳細設計 §4.5 / FR-01 / FR-11 / BR-01 / BR-09）。
 *
 * 書類テーブルとの直接リレーションを持たない（実行ログのみ）。
 * status は running → completed / failed に遷移する。
 */
class ShipmentFetchLog extends Model
{
    use HasFactory;

    /** 実行中 */
    public const STATUS_RUNNING = 'running';

    /** 正常完了 */
    public const STATUS_COMPLETED = 'completed';

    /** 失敗 */
    public const STATUS_FAILED = 'failed';

    /**
     * 許可された status 値の一覧（allowlist・NFR-S-06 / FR-11）。
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'started_at',
        'completed_at',
        'fetched_count',
        'created_delivery_note_count',
        'created_invoice_count',
        'skipped_count',
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
            'fetched_count' => 'integer',
            'created_delivery_note_count' => 'integer',
            'created_invoice_count' => 'integer',
            'skipped_count' => 'integer',
            'execution_seconds' => 'float',
        ];
    }

    /**
     * status による絞り込みスコープ。allowlist に含まれない値は無視する（NFR-S-06）。
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status !== null && in_array($status, self::statuses(), true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): ShipmentFetchLogFactory
    {
        return ShipmentFetchLogFactory::new();
    }
}
