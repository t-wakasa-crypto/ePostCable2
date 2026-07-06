<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * 書類（invoices / delivery_notes）が共通で持つステータス定数とスコープを
 * 提供するトレイト。
 *
 * ステータスは文字列リテラルの直書きを禁止し、必ず本定数を経由して扱う
 * （開発環境設計書 §4 / BR-01）。Invoice・DeliveryNote の両モデルで use する。
 */
trait HasDocumentStatuses
{
    /** 送信待ち */
    public const STATUS_PENDING = 'pending';

    /** 送信処理中（ジョブ投入済み） */
    public const STATUS_PROCESSING = 'processing';

    /** 送信成功 */
    public const STATUS_SENT = 'sent';

    /** 一時失敗（再試行可能） */
    public const STATUS_FAILED = 'failed';

    /** 恒久失敗（送付先0件・無効アドレス。手動対応が必要） */
    public const STATUS_FAILED_PERMANENT = 'failed_permanent';

    /**
     * 許可されたステータス値の一覧（allowlist・NFR-S-06）。
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_FAILED_PERMANENT,
        ];
    }

    /**
     * ステータスによる絞り込みスコープ。allowlist に含まれない値は無視する。
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status !== null && in_array($status, self::statuses(), true)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
