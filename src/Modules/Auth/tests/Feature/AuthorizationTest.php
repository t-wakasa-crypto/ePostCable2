<?php

/**
 * 認可（auth / admin 2段ミドルウェア）と CSRF を検証するテスト
 * （T023 / 詳細設計 §7 / FR-17 / NFR-S-02/05）。
 */

use Illuminate\Support\Facades\Route;
use Modules\User\Models\User;

beforeEach(function () {
    // admin ミドルウェアを検証するためのテスト専用ルートを定義する
    Route::middleware(['web', 'auth', 'admin'])->get('/__test_admin', fn () => 'ok');
});

it('未認証ユーザーは admin ルートで /login へリダイレクトされる', function () {
    $this->get('/__test_admin')->assertRedirect('/login');
});

it('一般ユーザーは admin ルートで 403 になる', function () {
    $user = User::factory()->create(); // general

    $this->actingAs($user)->get('/__test_admin')->assertForbidden();
});

it('管理者は admin ルートにアクセスできる', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/__test_admin')->assertOk();
});

it('login フォームに CSRF トークンフィールドが含まれる（全 POST を CSRF 保護）', function () {
    // web ミドルウェアグループの VerifyCsrfToken により全 POST が保護される。
    // ビューは @csrf で _token を出力する（NFR-S-05）。
    $this->get('/login')->assertSee('name="_token"', false);
});
