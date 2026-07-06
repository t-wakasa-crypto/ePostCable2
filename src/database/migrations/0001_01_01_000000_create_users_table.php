<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // users テーブル（db-design §2.1 / FR-12 / FR-16 / BR-08）
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                     // 表示名（日本語）
            $table->string('email')->unique();                          // ログインID・unique（BR-08）
            $table->string('password');                                 // bcrypt ハッシュ
            // role: general（一般）/ admin（管理者）。enum で CHECK 制約を表現（db-design ck_users_role）
            $table->enum('role', ['general', 'admin'])->default('general');
            $table->timestamp('retired_at')->nullable();                // NULL=現役 / 日時=退職済み（NFR-S-04）
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ。MySQL では string() が
        // そのまま varchar になるため何もしない）。email は unique 制約に含まれるため
        // 一旦 drop → ALTER → 再作成する（SQL Server はインデックス列を直接 ALTER できない）。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['email']);
            });

            SqlServerVarchar::alter('users', [
                'name' => 'varchar(255) NOT NULL',
                'email' => 'varchar(255) NOT NULL',
                'password' => 'varchar(255) NOT NULL',
                'role' => 'varchar(20) NOT NULL',
                'remember_token' => 'varchar(100) NULL',
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
