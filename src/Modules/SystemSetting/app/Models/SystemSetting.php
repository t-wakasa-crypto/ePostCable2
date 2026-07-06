<?php

namespace Modules\SystemSetting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * システム設定 KVS モデル（詳細設計 §4.6 / FR-13 / BR-06 / NFR-E-01）。
 *
 * リトライ・タイムアウト・通知先などの動作パラメータを画面から変更可能にする。
 * ジョブは get() で動的取得し、設定変更をワーカー再起動なしに反映する（NFR-M-04）。
 */
class SystemSetting extends Model
{
    /** integer 型（min_value〜max_value で範囲検証） */
    public const TYPE_INTEGER = 'integer';

    /** emails 型（1行1アドレス・改行区切り保存） */
    public const TYPE_EMAILS = 'emails';

    /** string 型 */
    public const TYPE_STRING = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'min_value',
        'max_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_value' => 'integer',
            'max_value' => 'integer',
        ];
    }

    /**
     * 設定値を取得する（詳細設計 §4.6）。
     *
     * 未登録時は $default（ジョブのフォールバック値。シーダー既定値と統一）を返す。
     * integer 型は数値へキャストして返す。
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::query()->where('key', $key)->first();

        if ($setting === null || $setting->value === null) {
            return $default;
        }

        if ($setting->type === self::TYPE_INTEGER) {
            return (int) $setting->value;
        }

        return $setting->value;
    }

    /**
     * 全メール共通の BCC アドレス配列を返す（FR-14）。
     *
     * mail_bcc_address を改行分割・trim・空除去して配列化する。未設定時は空配列。
     *
     * @return array<int, string>
     */
    public static function mailBccAddresses(): array
    {
        return self::splitEmails(self::get('mail_bcc_address'));
    }

    /**
     * バッチ完了通知先アドレス配列を返す（NFR-R-07）。
     *
     * admin_notification_emails を改行分割・trim・空除去して配列化する。未設定時は空配列。
     *
     * @return array<int, string>
     */
    public static function adminNotificationEmails(): array
    {
        return self::splitEmails(self::get('admin_notification_emails'));
    }

    /**
     * emails 型の値（改行区切り）を trim・空除去した配列へ変換する。
     *
     * @return array<int, string>
     */
    private static function splitEmails(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }
}
