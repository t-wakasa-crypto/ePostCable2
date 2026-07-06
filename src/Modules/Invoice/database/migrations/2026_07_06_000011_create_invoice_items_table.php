<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * invoice_items テーブル（db-design §2.3 / BR-09）。
 * 請求書明細。親 invoices との FK は cascade delete。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')                             // invoices.id 参照
                ->constrained('invoices')
                ->cascadeOnDelete()                                     // 親削除で明細も削除（BR-09）
                ->cascadeOnUpdate();
            $table->string('item_name', 500);                          // 品目名（日本語）
            $table->integer('quantity')->default(1);                   // 数量
            $table->decimal('unit_price', 14, 0)->default(0);          // 単価（円）
            $table->decimal('amount', 14, 0)->default(0);              // 金額（円）
            $table->integer('sort_order')->nullable();                 // 並び順（基幹システム由来）
            $table->timestamps();

            // FK インデックスを明示（SQL Server は FK に自動作成しない・db-design §3.1）
            $table->index('invoice_id', 'idx_invoice_items_invoice_id');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。item_name はインデックス非対象。
        SqlServerVarchar::alter('invoice_items', [
            'item_name' => 'varchar(500) NOT NULL',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
