<?php

namespace Modules\User\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\User\Models\User;

/**
 * User モデルのテスト用ファクトリ。
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_GENERAL,
            'retired_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 管理者ユーザーの state。
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * 退職済みユーザーの state。
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'retired_at' => now(),
        ]);
    }
}
