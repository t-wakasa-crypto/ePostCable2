<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * send_mail_logs テーブル（db-design §2.6 / FR-02 / FR-03 / BR-03 / BR-07）。
 * 送信バッチ実行の親ログ。手動再送まとめ親（manual-resend）も同テーブルに格納。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_mail_logs', function (Blueprint $table) {
            $table->id();
            // batch_key: バッチ種別。enum で CHECK 制約を表現（db-design ck_send_mail_logs_batch_key）
            $table->enum('batch_key', ['send-invoices', 'send-delivery-notes', 'manual-resend']);
            $table->string('batch_name', 100);                          // 表示名（日本語可）
            $table->timestamp('started_at')->nullable();                // 開始日時
            $table->timestamp('completed_at')->nullable();              // 正常完了日時
            $table->timestamp('failed_at')->nullable();                 // 失敗日時（displayStatus 最優先・BR-03）
            $table->integer('dispatched_count')->default(0);            // ジョブ投入件数
            $table->integer('reset_count')->default(0);                 // stuck 差し戻し件数
            $table->integer('retry_failed_count')->default(0);          // --retry-failed 差し戻し件数
            $table->float('execution_seconds')->nullable();             // 実行秒数
            $table->text('error_message')->nullable();                  // バッチ全体失敗時のエラー
            $table->timestamps();

            // バッチ種別フィルタ・当日 manual-resend 取得（BR-07・db-design §3.1）
            $table->index('batch_key', 'idx_send_mail_logs_batch_key');
            // ダッシュボード直近実行取得（FR-07）
            $table->index('started_at', 'idx_send_mail_logs_started_at');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。batch_key はインデックスに
        // 含まれるため drop → ALTER → 再作成する。error_message は text()（varchar(max)）。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('send_mail_logs', function (Blueprint $table) {
                $table->dropIndex('idx_send_mail_logs_batch_key');
            });

            SqlServerVarchar::alter('send_mail_logs', [
                'batch_key' => 'varchar(50) NOT NULL',
                'batch_name' => 'varchar(100) NOT NULL',
                'error_message' => 'varchar(max) NULL',
            ]);

            Schema::table('send_mail_logs', function (Blueprint $table) {
                $table->index('batch_key', 'idx_send_mail_logs_batch_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('send_mail_logs');
    }
};
