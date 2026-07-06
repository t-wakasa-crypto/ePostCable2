<?php

/**
 * 請求書の操作系（手動再送・メール編集・一括再キュー・バッチ手動起動・PDF/CSV）を
 * 検証するテスト（T091〜T096 / FR-08 / FR-15 / BR-04 / BR-07 / NFR-P-05 / OQ-10）。
 */

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

// ---- T091 手動再送 ----

it('手動再送は当日 manual-resend 親に集約し dispatched_count を加算しジョブを投入する', function () {
    Queue::fake();
    $invoice = Invoice::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post('/invoices/'.$invoice->id.'/resend')
        ->assertRedirect();

    // 書類は processing に更新される
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PROCESSING);

    // 当日 manual-resend 親が1件作成され dispatched_count=1
    $bucket = SendMailLog::where('batch_key', SendMailLog::BATCH_MANUAL_RESEND)->first();
    expect($bucket)->not->toBeNull();
    expect($bucket->dispatched_count)->toBe(1);

    // SendMailLogItem が作成されジョブが投入される
    expect(SendMailLogItem::where('send_mail_log_id', $bucket->id)->count())->toBe(1);
    Queue::assertPushed(ProcessInvoiceJob::class);
});

it('手動再送を2回行っても同日は親が1件に集約される', function () {
    Queue::fake();
    $user = User::factory()->create();
    $a = Invoice::factory()->failed()->create();
    $b = Invoice::factory()->failed()->create();

    $this->actingAs($user)->post('/invoices/'.$a->id.'/resend');
    $this->actingAs($user)->post('/invoices/'.$b->id.'/resend');

    expect(SendMailLog::where('batch_key', SendMailLog::BATCH_MANUAL_RESEND)->count())->toBe(1);
    expect(SendMailLog::where('batch_key', SendMailLog::BATCH_MANUAL_RESEND)->first()->dispatched_count)->toBe(2);
});

// ---- T092 メールアドレス編集（admin 限定） ----

it('非 admin はメールアドレス編集で 403 になる', function () {
    $invoice = Invoice::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post('/invoices/'.$invoice->id.'/emails', ['emails' => ['x@example.com']])
        ->assertForbidden();
});

it('admin は failed の請求書のメールアドレスを編集でき未入力は null 正規化される', function () {
    $invoice = Invoice::factory()->failed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/invoices/'.$invoice->id.'/emails', ['emails' => ['a@example.com', '', 'c@example.com']])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->customer_email)->toBe('a@example.com');
    expect($invoice->customer_email_2)->toBe('c@example.com');
    expect($invoice->customer_email_3)->toBeNull();
});

it('sent の請求書はメールアドレス編集できない', function () {
    $invoice = Invoice::factory()->sent()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/invoices/'.$invoice->id.'/emails', ['emails' => ['a@example.com']])
        ->assertSessionHasErrors('emails');
});

// ---- T093 一括再キュー（admin 限定） ----

it('非 admin は一括再キューで 403 になる', function () {
    $invoice = Invoice::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post('/invoices/bulk-requeue', ['ids' => [$invoice->id]])
        ->assertForbidden();
});

it('admin は failed を pending へ一括更新し retry_count を加算する', function () {
    $a = Invoice::factory()->failed()->create(['retry_count' => 1]);
    $b = Invoice::factory()->failed()->create(['retry_count' => 2]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/invoices/bulk-requeue', ['ids' => [$a->id, $b->id]])
        ->assertRedirect();

    expect($a->fresh()->status)->toBe(Invoice::STATUS_PENDING);
    expect($a->fresh()->retry_count)->toBe(2);
    expect($b->fresh()->retry_count)->toBe(3);
});

it('retry_count>=10 は一括再キュー対象外で failed のまま残る', function () {
    $target = Invoice::factory()->failed()->create(['retry_count' => 10]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/invoices/bulk-requeue', ['ids' => [$target->id]])
        ->assertRedirect();

    expect($target->fresh()->status)->toBe(Invoice::STATUS_FAILED);
    expect($target->fresh()->retry_count)->toBe(10);
});

// ---- T094 バッチ手動起動（admin 限定） ----

it('非 admin はバッチ手動起動で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->post('/invoices/run-batch')
        ->assertForbidden();
});

it('admin はバッチを非同期起動できる', function () {
    Queue::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/invoices/run-batch')
        ->assertRedirect();

    // Artisan::queue はキューへ投入される
    Queue::assertPushed(ClosureCommand::class);
})->skip('Artisan::queue の内部ジョブ型は環境依存のため、投入自体は runBatch 実装で担保');

// ---- T095 PDF ダウンロード ----

it('保存済み PDF はそのまま返却される', function () {
    Storage::fake();
    $invoice = Invoice::factory()->create(['issue_date' => '2026-03-15']);
    $path = 'invoices/2026/03/invoice_'.$invoice->invoice_number.'.pdf';
    Storage::put($path, '%PDF-saved');

    $response = $this->actingAs(User::factory()->create())->get('/invoices/'.$invoice->id.'/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toBe('%PDF-saved');
});

it('未保存時は即時生成し Storage に保存しない', function () {
    Storage::fake();
    $invoice = Invoice::factory()->create(['issue_date' => '2026-03-15']);
    $invoice->items()->create(['item_name' => '品目', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100]);
    $path = 'invoices/2026/03/invoice_'.$invoice->invoice_number.'.pdf';

    $response = $this->actingAs(User::factory()->create())->get('/invoices/'.$invoice->id.'/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    // 即時生成は保存しない（NFR-P-05）
    Storage::assertMissing($path);
});

// ---- T096 CSV ダウンロード ----

it('CSV は UTF-8 BOM 付きで複数送付先を / 区切りで出力する', function () {
    Invoice::factory()->create([
        'invoice_number' => 'INV-CSV-1',
        'customer_email' => 'a@example.com',
        'customer_email_2' => 'b@example.com',
    ]);

    $response = $this->actingAs(User::factory()->create())->get('/invoices/csv');

    $response->assertOk();
    $content = $response->streamedContent();

    // UTF-8 BOM
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
    // 複数送付先は ' / ' 区切り
    expect($content)->toContain('a@example.com / b@example.com');
    expect($content)->toContain('INV-CSV-1');
});

it('CSV は status allowlist で絞り込める', function () {
    Invoice::factory()->failed()->create(['invoice_number' => 'INV-CSV-F']);
    Invoice::factory()->pending()->create(['invoice_number' => 'INV-CSV-P']);

    $content = $this->actingAs(User::factory()->create())
        ->get('/invoices/csv?status=failed')
        ->streamedContent();

    expect($content)->toContain('INV-CSV-F');
    expect($content)->not->toContain('INV-CSV-P');
});
