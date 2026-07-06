<?php

/**
 * Invoice モデルのメソッド・スコープ・リレーションを検証するテスト
 * （T030 / 詳細設計 §4.1 / BR-01 / BR-04 / NFR-E-02）。
 *
 * recipientEmails（trim・空除去・最大3件）、status スコープ、items/sendMailLogItems
 * リレーションを確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;

uses(RefreshDatabase::class);

it('recipientEmails は trim・空除去した送付先配列を返す', function () {
    $invoice = Invoice::factory()->make([
        'customer_email' => '  a@example.com ',
        'customer_email_2' => '',
        'customer_email_3' => 'c@example.com',
    ]);

    expect($invoice->recipientEmails())->toBe(['a@example.com', 'c@example.com']);
});

it('recipientEmails は全て空なら空配列を返す', function () {
    $invoice = Invoice::factory()->make([
        'customer_email' => '   ',
        'customer_email_2' => null,
        'customer_email_3' => null,
    ]);

    expect($invoice->recipientEmails())->toBe([]);
});

it('status スコープは allowlist の値のみで絞り込む', function () {
    Invoice::factory()->pending()->count(2)->create();
    Invoice::factory()->failed()->create();

    expect(Invoice::query()->status('pending')->count())->toBe(2);
    expect(Invoice::query()->status('failed')->count())->toBe(1);
    // allowlist 外は無視され全件対象
    expect(Invoice::query()->status('invalid')->count())->toBe(3);
    expect(Invoice::query()->status(null)->count())->toBe(3);
});

it('items リレーションで明細を取得できる', function () {
    $invoice = Invoice::factory()->create();
    $invoice->items()->createMany([
        ['item_name' => '品目1', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100],
        ['item_name' => '品目2', 'quantity' => 2, 'unit_price' => 200, 'amount' => 400],
    ]);

    expect($invoice->items()->count())->toBe(2);
});

it('sendMailLogItems は morphMap 論理名 invoice で紐づく', function () {
    $invoice = Invoice::factory()->create();
    $log = SendMailLog::factory()->create();
    SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => $invoice->id,
    ]);

    expect($invoice->sendMailLogItems()->count())->toBe(1);
    // DB には論理名（'invoice'）が格納される（Q-15）
    expect($invoice->sendMailLogItems()->first()->sendable_type)->toBe('invoice');
});
