<?php

/**
 * SendInvoices コマンドを検証するテスト
 * （T072 / 詳細設計 §1.1.2 / FR-02 / NFR-R-01/04 / NFR-P-02/03/04）。
 *
 * stuck 差し戻し・retry-failed・limit 取得・行ロック・ジョブ投入・通知を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Mail\BatchSummaryMail;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('pending を processing 更新しジョブを投入する', function () {
    Queue::fake();
    Invoice::factory()->pending()->count(3)->create();

    $this->artisan('batch:send-invoices')->assertSuccessful();

    Queue::assertPushed(ProcessInvoiceJob::class, 3);
    expect(Invoice::where('status', 'processing')->count())->toBe(3);
    expect(SendMailLogItem::count())->toBe(3);

    $log = SendMailLog::where('batch_key', 'send-invoices')->first();
    expect($log->dispatched_count)->toBe(3);
    expect($log->completed_at)->not->toBeNull();
});

it('limit 件のみ取得しジョブを投入する', function () {
    Queue::fake();
    Invoice::factory()->pending()->count(5)->create();

    $this->artisan('batch:send-invoices', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(ProcessInvoiceJob::class, 2);
    expect(Invoice::where('status', 'processing')->count())->toBe(2);
});

it('stuck-timeout 超過の processing を pending へ差し戻す', function () {
    Queue::fake();
    $invoice = Invoice::factory()->processing()->create();
    // updated_at を 2時間前へ（Eloquent の自動更新を避けるため DB 直接更新）
    DB::table('invoices')->where('id', $invoice->id)->update(['updated_at' => now()->subHours(2)]);

    $this->artisan('batch:send-invoices', ['--stuck-timeout' => 60])->assertSuccessful();

    $log = SendMailLog::where('batch_key', 'send-invoices')->first();
    expect($log->reset_count)->toBe(1);
    // 差し戻し後に pending → processing で再投入され retry_count は +1 済み
    expect((int) $invoice->fresh()->retry_count)->toBe(1);
});

it('--retry-failed 指定時に failed を pending へ差し戻す', function () {
    Queue::fake();
    Invoice::factory()->failed()->count(2)->create();

    $this->artisan('batch:send-invoices', ['--retry-failed' => true])->assertSuccessful();

    $log = SendMailLog::where('batch_key', 'send-invoices')->first();
    expect($log->retry_failed_count)->toBe(2);
    Queue::assertPushed(ProcessInvoiceJob::class, 2);
});

it('admin_notification_emails 設定時に BatchSummaryMail を送信する', function () {
    Queue::fake();
    Mail::fake();
    SystemSetting::create(['key' => 'admin_notification_emails', 'value' => 'admin@example.com', 'type' => 'emails']);
    Invoice::factory()->pending()->create();

    $this->artisan('batch:send-invoices')->assertSuccessful();

    Mail::assertSent(BatchSummaryMail::class);
});

it('ロック取得失敗時はスキップしログを作らない', function () {
    Queue::fake();
    Cache::lock('batch:send-invoices', 10)->get();
    Invoice::factory()->pending()->create();

    $this->artisan('batch:send-invoices')->assertSuccessful();

    expect(SendMailLog::count())->toBe(0);
    Queue::assertNothingPushed();
});
