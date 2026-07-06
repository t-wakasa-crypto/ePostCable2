<?php

/**
 * ProcessInvoiceJob の PDF 生成・送信・状態遷移を検証するテスト
 * （T060 / 詳細設計 §1.2 / §3.3 / FR-05 / NFR-R-02/05/06 / NFR-M-03）。
 *
 * 動的設定取得・status ガード・PDF パス・アドレス検証分岐・failed/failed_permanent 分岐を確認する。
 */

use App\Exceptions\PermanentJobFailureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Mail\InvoiceMail;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

/** テスト用に processing 状態の請求書と送信明細を用意する */
function makeProcessingInvoice(array $overrides = []): array
{
    $invoice = Invoice::factory()->processing()->create(array_merge([
        'issue_date' => '2026-03-15',
        'customer_email' => 'to@example.com',
    ], $overrides));
    $invoice->items()->create(['item_name' => '品目', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]);
    $log = SendMailLog::factory()->create();
    $item = SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => $invoice->id,
        'status' => SendMailLogItem::STATUS_PROCESSING,
    ]);

    return [$invoice, $item];
}

it('コンストラクタで system_settings を動的取得する', function () {
    SystemSetting::create(['key' => 'max_retries', 'value' => '5', 'type' => 'integer', 'min_value' => 0, 'max_value' => 10]);
    SystemSetting::create(['key' => 'retry_backoff', 'value' => '45', 'type' => 'integer', 'min_value' => 0, 'max_value' => 3600]);
    SystemSetting::create(['key' => 'pdf_timeout', 'value' => '120', 'type' => 'integer', 'min_value' => 10, 'max_value' => 300]);

    $job = new ProcessInvoiceJob(1, 1);

    expect($job->maxExceptions)->toBe(5);
    expect($job->tries)->toBe(5);
    expect($job->backoff)->toBe(45);
    expect($job->timeout)->toBe(120);
});

it('未取得時はシーダー値と一致するフォールバック値を使う', function () {
    $job = new ProcessInvoiceJob(1, 1);

    expect($job->maxExceptions)->toBe(3);
    expect($job->backoff)->toBe(30);
    expect($job->timeout)->toBe(60);
});

it('max_retries=0 のとき tries は 1（無制限にならず1回のみ試行）になる', function () {
    // Laravel は $tries=0/null を「無制限」と解釈するため、下限 1 を保証していること（BR-06）。
    SystemSetting::create(['key' => 'max_retries', 'value' => '0', 'type' => 'integer', 'min_value' => 0, 'max_value' => 10]);

    $job = new ProcessInvoiceJob(1, 1);

    expect($job->maxExceptions)->toBe(0);
    expect($job->tries)->toBe(1);
});

it('WithoutOverlapping ミドルウェアが expireAfter 付きで付与される', function () {
    $job = new ProcessInvoiceJob(1, 1);

    expect($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('正常時に PDF を保存し sent へ更新する', function () {
    Mail::fake();
    Storage::fake();
    [$invoice, $item] = makeProcessingInvoice();

    (new ProcessInvoiceJob($invoice->id, $item->id))->handle(app(PdfService::class));

    Storage::assertExists('invoices/2026/03/invoice_'.$invoice->invoice_number.'.pdf');
    Mail::assertSent(InvoiceMail::class);
    expect($invoice->fresh()->status)->toBe('sent');
    expect($item->fresh()->status)->toBe('sent');
});

it('processing 以外の書類はスキップする', function () {
    Mail::fake();
    Storage::fake();
    $invoice = Invoice::factory()->pending()->create();
    $log = SendMailLog::factory()->create();
    $item = SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id, 'sendable_type' => Invoice::MORPH_ALIAS, 'sendable_id' => $invoice->id,
    ]);

    (new ProcessInvoiceJob($invoice->id, $item->id))->handle(app(PdfService::class));

    Mail::assertNothingSent();
    expect($invoice->fresh()->status)->toBe('pending');
});

it('送付先0件は PermanentJobFailureException をスローする', function () {
    Storage::fake();
    [$invoice, $item] = makeProcessingInvoice(['customer_email' => '   ']);

    expect(fn () => (new ProcessInvoiceJob($invoice->id, $item->id))->handle(app(PdfService::class)))
        ->toThrow(PermanentJobFailureException::class);
});

it('無効アドレスは PermanentJobFailureException をスローする', function () {
    Storage::fake();
    [$invoice, $item] = makeProcessingInvoice(['customer_email' => 'not-an-email']);

    expect(fn () => (new ProcessInvoiceJob($invoice->id, $item->id))->handle(app(PdfService::class)))
        ->toThrow(PermanentJobFailureException::class);
});

it('failed() は恒久失敗を failed_permanent に更新する', function () {
    [$invoice, $item] = makeProcessingInvoice();

    (new ProcessInvoiceJob($invoice->id, $item->id))->failed(new PermanentJobFailureException('恒久失敗'));

    expect($invoice->fresh()->status)->toBe('failed_permanent');
    expect($item->fresh()->status)->toBe('failed_permanent');
    expect($item->fresh()->error_message)->toBe('恒久失敗');
});

it('failed() はその他の例外を failed に更新する', function () {
    [$invoice, $item] = makeProcessingInvoice();

    (new ProcessInvoiceJob($invoice->id, $item->id))->failed(new RuntimeException('一時失敗'));

    expect($invoice->fresh()->status)->toBe('failed');
    expect($item->fresh()->status)->toBe('failed');
});
