<?php

/**
 * delivery_notes / delivery_note_items テーブルのスキーマが db-design §2.4 / §2.5 と
 * 一致することを検証するテスト（T013 / T014）。
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('delivery_notes テーブルが必要なカラムを持つ（delivery_date を含む）', function () {
    expect(Schema::hasColumns('delivery_notes', [
        'id', 'delivery_number', 'customer_name', 'customer_email',
        'customer_email_2', 'customer_email_3', 'amount', 'tax', 'tax_amount',
        'status', 'retry_count', 'sent_at', 'delivery_date', 'issue_date',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('delivery_note_items が必要なカラムを持つ', function () {
    expect(Schema::hasColumns('delivery_note_items', [
        'id', 'delivery_note_id', 'item_name', 'quantity', 'unit_price', 'amount', 'sort_order',
    ]))->toBeTrue();
});

it('親 delivery_note 削除で明細も cascade 削除される', function () {
    $id = DB::table('delivery_notes')->insertGetId([
        'delivery_number' => 'DN-1', 'customer_name' => 'テスト', 'customer_email' => 'a@example.com',
        'amount' => 1000, 'tax' => 10, 'tax_amount' => 100, 'status' => 'pending',
    ]);
    DB::table('delivery_note_items')->insert([
        'delivery_note_id' => $id, 'item_name' => '品目', 'quantity' => 1,
        'unit_price' => 1000, 'amount' => 1000,
    ]);

    DB::table('delivery_notes')->where('id', $id)->delete();

    expect(DB::table('delivery_note_items')->where('delivery_note_id', $id)->count())->toBe(0);
});
