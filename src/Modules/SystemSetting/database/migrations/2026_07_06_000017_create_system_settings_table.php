<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * system_settings テーブル（db-design §2.9 / FR-13 / BR-06 / NFR-E-01 / NFR-M-01）。
 * KVS 形式のシステム設定テーブル。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();                       // 設定キー（unique）
            $table->text('value')->nullable();                          // 設定値（改行区切り複数値も可）
            // type: バリデーション種別。enum で CHECK 制約を表現（db-design ck_system_settings_type）
            $table->enum('type', ['integer', 'emails', 'string'])->default('string');
            $table->integer('min_value')->nullable();                   // integer 型の下限（BR-06）
            $table->integer('max_value')->nullable();                   // integer 型の上限（BR-06）
            $table->timestamps();
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。key は unique に含まれるため
        // drop → ALTER → 再作成する。value は text()（varchar(max)）、type は enum（インデックス非対象）。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropUnique(['key']);
            });

            SqlServerVarchar::alter('system_settings', [
                'key' => 'varchar(100) NOT NULL',
                'value' => 'varchar(max) NULL',
                'type' => 'varchar(20) NOT NULL',
            ]);

            Schema::table('system_settings', function (Blueprint $table) {
                $table->unique('key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
