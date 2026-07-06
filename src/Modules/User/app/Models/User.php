<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\User\Database\Factories\UserFactory;

/**
 * ログインユーザーモデル（詳細設計 §4.7 / FR-12 / FR-16 / BR-08）。
 *
 * role で権限（general / admin）を区別し、retired_at で退職者を識別する。
 * 退職者はログイン不可（NFR-S-04）。
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** 一般（担当者） */
    public const ROLE_GENERAL = 'general';

    /** 管理者 */
    public const ROLE_ADMIN = 'admin';

    /**
     * 許可された role 値の一覧（allowlist・NFR-S-06）。
     *
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [self::ROLE_GENERAL, self::ROLE_ADMIN];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'retired_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 管理者かどうか（admin ミドルウェアの判定に使用・FR-17）。
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * 退職者かどうか（ログイン拒否判定・FR-16 / NFR-S-04）。
     */
    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * 現役ユーザー（退職者を除外）に絞り込むスコープ（FR-12）。
     * include_retired 指定時はこのスコープを適用しないことで退職者も表示する。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    /**
     * role による絞り込みスコープ。allowlist に含まれない値は無視する（NFR-S-06）。
     */
    public function scopeRole(Builder $query, ?string $role): Builder
    {
        if ($role !== null && in_array($role, self::roles(), true)) {
            $query->where('role', $role);
        }

        return $query;
    }

    /**
     * モジュール内のファクトリを使用する。
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
