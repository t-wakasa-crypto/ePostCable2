<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * shipment_fetch_logs テーブル（db-design §2.8 / FR-01 / FR-11 / BR-01 / BR-09）。
 * 出荷取得バッチの実行ログ。書類テーブルとの直接リレーションは持たない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_fetch_logs', function (Blueprint $table) {
            $table->id();
            // status: running → completed / failed（BR-01）。enum で CHECK 制約を表現
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('started_at')->nullable();                // 開始日時
            $table->timestamp('completed_at')->nullable();              // 正常完了日時
            $table->integer('fetched_count')->default(0);               // 基幹API取得件数
            $table->integer('created_delivery_note_count')->default(0); // 新規作成納品書数
            $table->integer('created_invoice_count')->default(0);       // 新規作成請求書数
            $table->integer('skipped_count')->default(0);               // 重複スキップ件数（BR-05）
            $table->float('execution_seconds')->nullable();             // 実行秒数
            $table->text('error_message')->nullable();                  // 例外メッセージ
            $table->timestamps();

            $table->index('status', 'idx_shipment_fetch_logs_status');           // フィルタ（FR-11）
            $table->index('started_at', 'idx_shipment_fetch_logs_started_at');   // 直近取得（FR-07）
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。status はインデックスに
        // 含まれるため drop → ALTER → 再作成する。error_message は text()（varchar(max)）。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('shipment_fetch_logs', function (Blueprint $table) {
                $table->dropIndex('idx_shipment_fetch_logs_status');
            });

            SqlServerVarchar::alter('shipment_fetch_logs', [
                'status' => 'varchar(20) NOT NULL',
                'error_message' => 'varchar(max) NULL',
            ]);

            Schema::table('shipment_fetch_logs', function (Blueprint $table) {
                $table->index('status', 'idx_shipment_fetch_logs_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_fetch_logs');
    }
};
