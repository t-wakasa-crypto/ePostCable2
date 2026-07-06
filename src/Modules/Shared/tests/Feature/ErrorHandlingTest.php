<?php

/**
 * エラーハンドリング方針（詳細設計 §6）の統一検証テスト（T110 / NFR-R / NFR-M-03）。
 *
 * 各層（Command・Job）の失敗事象が方針表どおりの記録先・状態遷移になることを横断的に確認する。
 * 個別の受入条件は各モジュールのテストでも検証済みだが、本テストは §6 の方針表そのものを
 * 一枚のテストとして担保することを目的とする（記録先・遷移の一貫性検証）。
 */

use App\Exceptions\PermanentJobFailureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;
use Modules\ShipmentFetch\Services\ShipmentFetchService;

uses(RefreshDatabase::class);

/** processing 状態の請求書と送信明細を用意する */
function makeErrorHandlingInvoice(): array
{
    $invoice = Invoice::factory()->processing()->create(['customer_email' => 'to@example.com']);
    $log = SendMailLog::factory()->create();
    $item = SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => $invoice->id,
        'status' => SendMailLogItem::STATUS_PROCESSING,
    ]);

    return [$invoice, $item];
}

it('§6 取得Command: 基幹API例外は ShipmentFetchLog を failed にし error_message を記録する', function () {
    // ShipmentFetchService::fetch() が例外を投げるケース（API/接続例外）
    $mock = Mockery::mock(ShipmentFetchService::class);
    $mock->shouldReceive('fetch')->andThrow(new RuntimeException('基幹API接続に失敗しました'));
    app()->instance(ShipmentFetchService::class, $mock);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $log = ShipmentFetchLog::first();
    expect($log->status)->toBe(ShipmentFetchLog::STATUS_FAILED);
    expect($log->error_message)->toContain('基幹API接続に失敗しました');
});

it('§6 Job: PDF空出力等の一時失敗（非 Permanent 例外）は failed に遷移する', function () {
    [$invoice, $item] = makeErrorHandlingInvoice();

    // PDF 空出力は PdfService が RuntimeException を投げる → 非 Permanent のため failed（NFR-R-05 リトライ枯渇後）
    (new ProcessInvoiceJob($invoice->id, $item->id))->failed(new RuntimeException('PDF出力が空です'));

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_FAILED);
    expect($item->fresh()->status)->toBe(SendMailLogItem::STATUS_FAILED);
});

it('§6 Job: 恒久失敗（送付先0件・無効アドレス）は failed_permanent に遷移する', function () {
    [$invoice, $item] = makeErrorHandlingInvoice();

    (new ProcessInvoiceJob($invoice->id, $item->id))->failed(new PermanentJobFailureException('送付先メールアドレスが0件です。'));

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_FAILED_PERMANENT);
    expect($item->fresh()->status)->toBe(SendMailLogItem::STATUS_FAILED_PERMANENT);
});

it('§6 エラーメッセージ規約: error_message は1000文字に切り詰めて記録する', function () {
    [$invoice, $item] = makeErrorHandlingInvoice();

    // 2000 文字のメッセージ → mb_substr で 1000 文字に切り詰められる（NFR-M-03）
    $longMessage = str_repeat('あ', 2000);
    (new ProcessInvoiceJob($invoice->id, $item->id))->failed(new RuntimeException($longMessage));

    expect(mb_strlen($item->fresh()->error_message))->toBe(1000);
});
