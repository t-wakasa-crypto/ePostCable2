<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\SystemSetting\Database\Seeders\SystemSettingSeeder;
use Modules\User\Database\Seeders\AdminUserSeeder;

/**
 * アプリ全体のシーダー起点（db-design §4 / §5.5）。
 * system_settings 初期値と開発用管理者を投入する。
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SystemSettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
