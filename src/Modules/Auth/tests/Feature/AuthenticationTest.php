<?php

/**
 * 認証（ログイン・ログアウト・退職者拒否・試行回数制限）を検証するテスト
 * （T021 / T022 / 詳細設計 §1.4.8 / §3.5 / FR-16 / NFR-S-01/03/04）。
 */

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\User\Models\User;

it('未認証ユーザーは保護ルートから /login へリダイレクトされる', function () {
    $this->get('/')->assertRedirect('/login');
});

it('ログイン画面が表示され CSRF トークンを含む', function () {
    // @csrf により _token フィールドが出力される（NFR-S-05）
    $this->get('/login')
        ->assertOk()
        ->assertSee('name="_token"', false);
});

it('正しい資格情報でログインでき、ダッシュボードへ遷移する', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('誤ったパスワードではログインできない', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('退職者は正しい資格情報でもログインを拒否される', function () {
    $user = User::factory()->retired()->create(['password' => 'secret123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('ログアウトできる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('メール+IP で5回失敗するとロックされる', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    // 5回失敗させる
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // 6回目は正しいパスワードでもロックによりログインできない
    $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('ログイン成功で試行回数カウンタがリセットされる', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    // 4回失敗（ロック閾値未満）
    for ($i = 0; $i < 4; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    // 正しくログイン → カウンタがクリアされる
    $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
        ->assertRedirect('/');

    $key = Str::transliterate(
        Str::lower($user->email).'|127.0.0.1'
    );
    expect(RateLimiter::attempts($key))->toBe(0);
});
