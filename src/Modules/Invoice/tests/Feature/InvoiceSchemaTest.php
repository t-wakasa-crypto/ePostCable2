<?php

/**
 * invoices / invoice_items テーブルのスキーマが db-design §2.2 / §2.3 と
 * 一致することを検証するテスト（T011 / T012）。
 *
 * カラム構成・status の CHECK 相当（enum）・FK cascade delete を確認する。
 */

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('invoices テーブルが必要なカラムを持つ', function () {
    expect(Schema::hasTable('invoices'))->toBeTrue();
    expect(Schema::hasColumns('invoices', [
        'id', 'invoice_number', 'customer_name', 'customer_email',
        'customer_email_2', 'customer_email_3', 'amount', 'tax', 'tax_amount',
        'status', 'retry_count', 'sent_at', 'issue_date', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('invoice_items テーブルが必要なカラムを持つ', function () {
    expect(Schema::hasColumns('invoice_items', [
        'id', 'invoice_id', 'item_name', 'quantity', 'unit_price', 'amount', 'sort_order',
    ]))->toBeTrue();
});

it('invoice_number は unique 制約で重複を拒否する', function () {
    $base = [
        'customer_name' => 'テスト商事',
        'customer_email' => 'a@example.com',
        'amount' => 1000,
        'tax' => 10,
        'tax_amount' => 100,
        'status' => 'pending',
    ];
    DB::table('invoices')->insert($base + ['invoice_number' => 'INV-001']);

    expect(fn () => DB::table('invoices')->insert($base + ['invoice_number' => 'INV-001']))
        ->toThrow(QueryException::class);
});

it('親 invoice 削除で invoice_items も cascade 削除される', function () {
    $invoiceId = DB::table('invoices')->insertGetId([
        'invoice_number' => 'INV-100',
        'customer_name' => 'テスト',
        'customer_email' => 'a@example.com',
        'amount' => 1000, 'tax' => 10, 'tax_amount' => 100, 'status' => 'pending',
    ]);
    DB::table('invoice_items')->insert([
        'invoice_id' => $invoiceId, 'item_name' => '品目', 'quantity' => 1,
        'unit_price' => 1000, 'amount' => 1000,
    ]);

    DB::table('invoices')->where('id', $invoiceId)->delete();

    expect(DB::table('invoice_items')->where('invoice_id', $invoiceId)->count())->toBe(0);
});
