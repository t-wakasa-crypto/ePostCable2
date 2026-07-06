<?php

/**
 * User モデルのメソッド・スコープを検証するテスト（T020 / 詳細設計 §4.7）。
 *
 * isAdmin() / isRetired() / 退職者除外スコープ / role フィルタスコープ /
 * password ハッシュ化を確認する（BR-08 / FR-12 / FR-16）。
 */

use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;

it('isAdmin は admin ロールで true を返す', function () {
    expect(User::factory()->admin()->make()->isAdmin())->toBeTrue();
    expect(User::factory()->make()->isAdmin())->toBeFalse();
});

it('isRetired は retired_at がセットされていると true を返す', function () {
    expect(User::factory()->retired()->make()->isRetired())->toBeTrue();
    expect(User::factory()->make()->isRetired())->toBeFalse();
});

it('active スコープは退職者を除外する', function () {
    User::factory()->count(2)->create();
    User::factory()->retired()->create();

    expect(User::query()->active()->count())->toBe(2);
    expect(User::query()->count())->toBe(3);
});

it('role スコープは allowlist の値のみで絞り込む', function () {
    User::factory()->admin()->create();
    User::factory()->count(2)->create(); // general

    expect(User::query()->role('admin')->count())->toBe(1);
    expect(User::query()->role('general')->count())->toBe(2);
    // allowlist 外の値は無視され、全件が対象になる
    expect(User::query()->role('invalid-role')->count())->toBe(3);
    expect(User::query()->role(null)->count())->toBe(3);
});

it('password は代入時にハッシュ化される', function () {
    $user = User::factory()->create(['password' => 'plain-password']);

    expect($user->password)->not->toBe('plain-password');
    expect(Hash::check('plain-password', $user->password))->toBeTrue();
});
