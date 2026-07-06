<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Database\Support\SqlServerVarchar;

/**
 * delivery_note_items テーブル（db-design §2.5 / BR-09）。
 * 納品書明細。親 delivery_notes との FK は cascade delete。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')
                ->constrained('delivery_notes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('item_name', 500);                          // 品目名（日本語）
            $table->integer('quantity')->default(1);                   // 数量
            $table->decimal('unit_price', 14, 0)->default(0);          // 単価（円）
            $table->decimal('amount', 14, 0)->default(0);              // 金額（円）
            $table->integer('sort_order')->nullable();                 // 並び順
            $table->timestamps();

            $table->index('delivery_note_id', 'idx_delivery_note_items_delivery_note_id');
        });

        // 文字列カラムを varchar 型へ統一（Q-17・sqlsrv のみ）。item_name はインデックス非対象。
        SqlServerVarchar::alter('delivery_note_items', [
            'item_name' => 'varchar(500) NOT NULL',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
