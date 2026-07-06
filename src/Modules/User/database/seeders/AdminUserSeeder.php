<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;

/**
 * 開発環境用の初期管理者ユーザーを投入するシーダー（db-design §4.2 / FR-12）。
 *
 * email をキーに冪等投入する。本番環境ではパスワードを環境変数から読み込むか
 * 初回ログイン後の変更を必須とすること。
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'admin@example.com')],
            [
                'name' => '管理者',
                'password' => Hash::make(env('ADMIN_SEED_PASSWORD', 'password')),
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
