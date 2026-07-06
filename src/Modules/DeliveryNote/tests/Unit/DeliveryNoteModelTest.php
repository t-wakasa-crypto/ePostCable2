<?php

/**
 * DeliveryNote モデルのメソッド・スコープ・リレーションを検証するテスト
 * （T030 / 詳細設計 §4.1 / BR-01 / BR-04 / NFR-E-02）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;

uses(RefreshDatabase::class);

it('recipientEmails は trim・空除去した送付先配列を返す', function () {
    $note = DeliveryNote::factory()->make([
        'customer_email' => ' a@example.com',
        'customer_email_2' => '  ',
        'customer_email_3' => 'c@example.com ',
    ]);

    expect($note->recipientEmails())->toBe(['a@example.com', 'c@example.com']);
});

it('status スコープは allowlist の値のみで絞り込む', function () {
    DeliveryNote::factory()->pending()->count(2)->create();
    DeliveryNote::factory()->sent()->create();

    expect(DeliveryNote::query()->status('pending')->count())->toBe(2);
    expect(DeliveryNote::query()->status('sent')->count())->toBe(1);
    expect(DeliveryNote::query()->status('invalid')->count())->toBe(3);
});

it('sendMailLogItems は morphMap 論理名 delivery_note で紐づく', function () {
    $note = DeliveryNote::factory()->create();
    $log = SendMailLog::factory()->create();
    SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => DeliveryNote::MORPH_ALIAS,
        'sendable_id' => $note->id,
    ]);

    expect($note->sendMailLogItems()->count())->toBe(1);
    expect($note->sendMailLogItems()->first()->sendable_type)->toBe('delivery_note');
});
