<?php

/**
 * ShipmentFetchService の疎結合動作を検証するテスト
 * （T040 / 詳細設計 §1.3.1 / FR-01 / NFR-P-06 / NFR-E-04）。
 *
 * 未設定時は空配列、設定時は HTTP Client（timeout 30）でレスポンスを配列化することを確認する。
 */

use Illuminate\Support\Facades\Http;
use Modules\ShipmentFetch\Services\ShipmentFetchService;

it('BACKBONE_API_URL 未設定時は空配列を返す', function () {
    config(['services.backbone.url' => null]);

    expect((new ShipmentFetchService)->fetch())->toBe([]);
});

it('設定時は API レスポンスを配列で返す', function () {
    config(['services.backbone.url' => 'https://backbone.example.com/shipments']);

    Http::fake([
        'backbone.example.com/*' => Http::response([
            ['invoice_number' => 'INV-1', 'amount' => 1000],
        ], 200),
    ]);

    $result = (new ShipmentFetchService)->fetch();

    expect($result)->toHaveCount(1);
    expect($result[0]['invoice_number'])->toBe('INV-1');
});

it('データ契約（§2.3）どおりに1件を内部キーへ整形する', function () {
    // T111: 基幹 API レスポンスの各項目を内部の出荷データ契約へマッピングする
    config(['services.backbone.url' => 'https://backbone.example.com/shipments']);

    Http::fake([
        'backbone.example.com/*' => Http::response([
            [
                'delivery_number' => 'DN-1',
                'invoice_number' => 'INV-1',
                'customer_name' => 'テスト商事',
                'customer_email' => 'a@example.com',
                'customer_email_2' => 'b@example.com',
                'amount' => '1500',
                'delivery_date' => '2026-05-01',
                'issue_date' => '2026-05-10',
                'items' => [
                    ['item_name' => '商品A', 'quantity' => 2, 'unit_price' => 500, 'amount' => 1000],
                ],
                'fixed_items' => [
                    ['item_name' => '送料', 'amount' => 500],
                ],
            ],
        ], 200),
    ]);

    $result = (new ShipmentFetchService)->fetch();

    expect($result)->toHaveCount(1);
    $s = $result[0];
    expect($s['delivery_number'])->toBe('DN-1');
    expect($s['invoice_number'])->toBe('INV-1');
    expect($s['customer_name'])->toBe('テスト商事');
    expect($s['customer_email'])->toBe('a@example.com');
    expect($s['customer_email_2'])->toBe('b@example.com');
    expect($s['customer_email_3'])->toBeNull();
    // amount は整数へキャストされる
    expect($s['amount'])->toBe(1500);
    expect($s['items'])->toHaveCount(1);
    expect($s['fixed_items'])->toHaveCount(1);
});

it('customer_emails 配列形式の送付先を最大3件へ整形する', function () {
    config(['services.backbone.url' => 'https://backbone.example.com/shipments']);

    Http::fake([
        'backbone.example.com/*' => Http::response([
            [
                'invoice_number' => 'INV-2',
                'customer_emails' => ['x@example.com', 'y@example.com', 'z@example.com', 'over@example.com'],
                'amount' => 100,
            ],
        ], 200),
    ]);

    $s = (new ShipmentFetchService)->fetch()[0];

    expect($s['customer_email'])->toBe('x@example.com');
    expect($s['customer_email_2'])->toBe('y@example.com');
    expect($s['customer_email_3'])->toBe('z@example.com');
});

it('data ラッパーキー配下の出荷データ配列を整形する', function () {
    config(['services.backbone.url' => 'https://backbone.example.com/shipments']);

    Http::fake([
        'backbone.example.com/*' => Http::response([
            'data' => [
                ['invoice_number' => 'INV-3', 'amount' => 200],
                ['invoice_number' => 'INV-4', 'amount' => 300],
            ],
        ], 200),
    ]);

    $result = (new ShipmentFetchService)->fetch();

    expect($result)->toHaveCount(2);
    expect($result[0]['invoice_number'])->toBe('INV-3');
    expect($result[1]['invoice_number'])->toBe('INV-4');
});
