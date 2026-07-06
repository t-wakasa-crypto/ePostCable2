<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * invoices テーブル（db-design §2.2 / FR-01 / BR-01 / BR-02 / BR-05）。
 * 出荷取得バッチで作成される請求書本体。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100)->unique();            // 請求書番号・重複スキップキー（BR-05）
            $table->string('customer_name');                            // 顧客名（日本語）
            $table->string('customer_email');                           // 送付先1（空白は出荷取得バッチで弾く・Q-09）
            $table->string('customer_email_2')->nullable();             // 送付先2
            $table->string('customer_email_3')->nullable();             // 送付先3
            $table->decimal('amount', 14, 0);                           // 税抜金額（円）
            $table->decimal('tax', 12, 0)->default(10);                 // 税率（%）。出荷取得時10固定（BR-02）
            $table->decimal('tax_amount', 14, 0);                       // 税額（円）= round(amount×tax/100)
            // status: 状態遷移（BR-01）。enum で CHECK 制約を表現
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'failed_permanent'])
                ->default('pending');
            $table->integer('retry_count')->default(0);                 // リトライ累積（記録用カウンタ・Q-05）
            $table->timestamp('sent_at')->nullable();                   // 送信成功日時
            $table->timestamp('issue_date')->nullable();                // 請求日（PDF パス年月基準）
            $table->timestamps();

            // 送信待ち取得の最適化（NFR-P-02/03・db-design §3.1）
            $table->index(['status', 'created_at'], 'idx_invoices_status_created_at');
            $table->index('status', 'idx_invoices_status');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。invoice_number は unique、
        // status はインデックスに含まれるため drop → ALTER → 再作成する。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique(['invoice_number']);
                $table->dropIndex('idx_invoices_status_created_at');
                $table->dropIndex('idx_invoices_status');
            });

            SqlServerVarchar::alter('invoices', [
                'invoice_number' => 'varchar(100) NOT NULL',
                'customer_name' => 'varchar(255) NOT NULL',
                'customer_email' => 'varchar(255) NOT NULL',
                'customer_email_2' => 'varchar(255) NULL',
                'customer_email_3' => 'varchar(255) NULL',
                'status' => 'varchar(30) NOT NULL',
            ]);

            Schema::table('invoices', function (Blueprint $table) {
                $table->unique('invoice_number');
                $table->index(['status', 'created_at'], 'idx_invoices_status_created_at');
                $table->index('status', 'idx_invoices_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
