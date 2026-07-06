<?php

namespace Modules\SystemSetting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * system_settings 初期データ投入シーダー（db-design §4.1 / FR-13 / BR-06）。
 *
 * ジョブのフォールバック値はここで投入するシーダー値と一致させている
 * （pdf_timeout=60 / retry_backoff=30 / max_retries=3・OQ-01 決定）。
 * updateOrInsert で冪等に投入し、既存値は上書きしない（value は変更しない）。
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // [key, value, type, min_value, max_value]
        $settings = [
            ['pdf_timeout', '60', 'integer', 10, 300],       // PDF 生成タイムアウト秒
            ['retry_backoff', '30', 'integer', 0, 3600],     // リトライ間隔秒
            ['max_retries', '3', 'integer', 0, 10],          // 最大リトライ回数
            ['admin_notification_emails', null, 'emails', null, null], // バッチ完了通知先
            ['mail_bcc_address', null, 'emails', null, null], // 全メール BCC 先
        ];

        foreach ($settings as [$key, $value, $type, $min, $max]) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $type,
                    'min_value' => $min,
                    'max_value' => $max,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
