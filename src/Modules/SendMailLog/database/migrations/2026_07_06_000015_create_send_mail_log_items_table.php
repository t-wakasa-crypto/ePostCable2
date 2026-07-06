<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * send_mail_log_items テーブル（db-design §2.7 / NFR-E-02 / NFR-M-03 / BR-09）。
 * 書類1通単位の送信明細ログ。ポリモーフィック関連（sendable）で
 * Invoice / DeliveryNote を参照する。
 *
 * send_mail_log_id は restrictOnDelete（親レコード削除不可・削除機能が存在も計画も
 * ないため孤立明細を防ぐ・OQ-06 決定）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_mail_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('send_mail_log_id')
                ->constrained('send_mail_logs')
                ->restrictOnDelete()                                   // 親削除不可（BR-09）
                ->cascadeOnUpdate();
            $table->string('sendable_type', 100);                      // モデルのフル修飾クラス名 / morphMap エイリアス
            $table->unsignedBigInteger('sendable_id');                 // 参照先主キー
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'failed_permanent'])
                ->default('pending');
            $table->string('error_message', 1000)->nullable();         // エラー（1000字上限・NFR-M-03）
            $table->timestamp('sent_at')->nullable();                  // 送信成功日時
            $table->timestamps();

            // 親子結合（詳細50件表示・FR-10）
            $table->index('send_mail_log_id', 'idx_send_mail_log_items_log_id');
            // sendable 逆引き（invoices.show 全履歴・FR-08）
            $table->index(['sendable_type', 'sendable_id'], 'idx_send_mail_log_items_sendable');
            // status フィルタ（has_pending/has_sent/has_failure・FR-10）
            $table->index('status', 'idx_send_mail_log_items_status');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。sendable_type は複合
        // インデックスに、status はインデックスに含まれるため drop → ALTER → 再作成する。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('send_mail_log_items', function (Blueprint $table) {
                $table->dropIndex('idx_send_mail_log_items_sendable');
                $table->dropIndex('idx_send_mail_log_items_status');
            });

            SqlServerVarchar::alter('send_mail_log_items', [
                'sendable_type' => 'varchar(100) NOT NULL',
                'status' => 'varchar(30) NOT NULL',
                'error_message' => 'varchar(1000) NULL',
            ]);

            Schema::table('send_mail_log_items', function (Blueprint $table) {
                $table->index(['sendable_type', 'sendable_id'], 'idx_send_mail_log_items_sendable');
                $table->index('status', 'idx_send_mail_log_items_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('send_mail_log_items');
    }
};
