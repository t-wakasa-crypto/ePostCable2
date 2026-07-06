<?php

/**
 * ユーザー管理（CRUD・admin 限定）を検証するテスト（T103 / FR-12 / BR-08）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('非 admin はユーザー一覧で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->get('/users')
        ->assertForbidden();
});

it('admin は一覧で退職者を既定除外し include_retired で表示できる', function () {
    User::factory()->create(['name' => 'ACTIVE-USER']);
    User::factory()->retired()->create(['name' => 'RETIRED-USER']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/users')
        ->assertOk()
        ->assertSee('ACTIVE-USER')
        ->assertDontSee('RETIRED-USER');

    $this->actingAs($admin)->get('/users?include_retired=1')
        ->assertOk()
        ->assertSee('RETIRED-USER');
});

it('admin は role フィルタ（allowlist）で絞り込める', function () {
    User::factory()->create(['name' => 'GENERAL-U']);
    User::factory()->admin()->create(['name' => 'ADMIN-U']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/users?role=general')
        ->assertOk()
        ->assertSee('GENERAL-U')
        ->assertDontSee('ADMIN-U');
});

it('admin はユーザーを作成できる（email unique / password confirmed / role in）', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'name' => '新規太郎',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_GENERAL,
        ])
        ->assertRedirect(route('users.index'));

    $created = User::where('email', 'new@example.com')->first();
    expect($created)->not->toBeNull();
    expect(Hash::check('password123', $created->password))->toBeTrue();
});

it('作成時のバリデーション違反（password 不一致）で作成されない', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/users', [
            'name' => 'x',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => User::ROLE_GENERAL,
        ])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'x@example.com')->exists())->toBeFalse();
});

it('編集で password は入力時のみ更新され retired トグルで退職状態を切り替えられる', function () {
    $target = User::factory()->create(['role' => User::ROLE_GENERAL]);
    $oldHash = $target->password;

    // password 未入力・retired=1
    $this->actingAs(User::factory()->admin()->create())
        ->put('/users/'.$target->id, [
            'name' => '更新名',
            'email' => $target->email,
            'role' => User::ROLE_ADMIN,
            'retired' => 1,
        ])
        ->assertRedirect(route('users.index'));

    $target->refresh();
    expect($target->name)->toBe('更新名');
    expect($target->role)->toBe(User::ROLE_ADMIN);
    expect($target->password)->toBe($oldHash); // password 未変更
    expect($target->isRetired())->toBeTrue();

    // retired 解除
    $this->actingAs(User::factory()->admin()->create())
        ->put('/users/'.$target->id, [
            'name' => '更新名',
            'email' => $target->email,
            'role' => User::ROLE_ADMIN,
        ]);
    expect($target->fresh()->isRetired())->toBeFalse();
});

it('自分自身は削除できない', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete('/users/'.$admin->id)
        ->assertSessionHasErrors('user');

    expect(User::find($admin->id))->not->toBeNull();
});

it('admin は他ユーザーを物理削除できる', function () {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete('/users/'.$target->id)
        ->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull();
});
