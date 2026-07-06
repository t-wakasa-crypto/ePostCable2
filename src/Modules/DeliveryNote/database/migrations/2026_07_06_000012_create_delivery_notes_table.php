<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * delivery_notes テーブル（db-design §2.4 / FR-01 / BR-01 / BR-02 / BR-05）。
 * 出荷取得バッチで作成される納品書本体。invoices と対称だが delivery_date を持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 100)->unique();           // 納品書番号・重複スキップキー（BR-05）
            $table->string('customer_name');                            // 顧客名（日本語）
            $table->string('customer_email');                           // 送付先1（空白は出荷取得バッチで弾く・Q-09）
            $table->string('customer_email_2')->nullable();             // 送付先2
            $table->string('customer_email_3')->nullable();             // 送付先3
            $table->decimal('amount', 14, 0);                           // 税抜金額（円）
            $table->decimal('tax', 12, 0)->default(10);                 // 税率（%）・BR-02
            $table->decimal('tax_amount', 14, 0);                       // 税額（円）
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'failed_permanent'])
                ->default('pending');
            $table->integer('retry_count')->default(0);                 // リトライ累積（記録用カウンタ・Q-05）
            $table->timestamp('sent_at')->nullable();                   // 送信成功日時
            $table->timestamp('delivery_date')->nullable();             // 納品日（PDF パス年月基準・Q-11）
            $table->timestamp('issue_date')->nullable();                // 発行日
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_delivery_notes_status_created_at');
            $table->index('status', 'idx_delivery_notes_status');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。delivery_number は unique、
        // status はインデックスに含まれるため drop → ALTER → 再作成する。
        if (SqlServerVarchar::isSqlServer()) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->dropUnique(['delivery_number']);
                $table->dropIndex('idx_delivery_notes_status_created_at');
                $table->dropIndex('idx_delivery_notes_status');
            });

            SqlServerVarchar::alter('delivery_notes', [
                'delivery_number' => 'varchar(100) NOT NULL',
                'customer_name' => 'varchar(255) NOT NULL',
                'customer_email' => 'varchar(255) NOT NULL',
                'customer_email_2' => 'varchar(255) NULL',
                'customer_email_3' => 'varchar(255) NULL',
                'status' => 'varchar(30) NOT NULL',
            ]);

            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->unique('delivery_number');
                $table->index(['status', 'created_at'], 'idx_delivery_notes_status_created_at');
                $table->index('status', 'idx_delivery_notes_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
