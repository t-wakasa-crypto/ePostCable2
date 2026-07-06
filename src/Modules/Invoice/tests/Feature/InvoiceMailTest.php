<?php

/**
 * InvoiceMail の件名・添付・BCC を検証するテスト（T070 / 詳細設計 §1.5 / FR-14）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Modules\Invoice\Mail\InvoiceMail;
use Modules\Invoice\Models\Invoice;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('件名は【請求書】{invoice_number} 形式', function () {
    $invoice = Invoice::factory()->create(['invoice_number' => 'INV-777']);
    $mail = new InvoiceMail($invoice, '%PDF-dummy');

    expect($mail->envelope()->subject)->toBe('【請求書】INV-777');
});

it('PDF を添付する', function () {
    $invoice = Invoice::factory()->create();
    $mail = new InvoiceMail($invoice, '%PDF-dummy');

    $mail->assertHasAttachment(
        Attachment::fromData(fn () => '%PDF-dummy', 'invoice_'.$invoice->invoice_number.'.pdf')
            ->withMime('application/pdf')
    );
});

it('設定済み mail_bcc_address を BCC に付与する', function () {
    SystemSetting::create(['key' => 'mail_bcc_address', 'value' => "bcc1@example.com\nbcc2@example.com", 'type' => 'emails']);
    $invoice = Invoice::factory()->create();

    $bcc = collect((new InvoiceMail($invoice, 'x'))->envelope()->bcc)->pluck('address')->all();

    expect($bcc)->toBe(['bcc1@example.com', 'bcc2@example.com']);
});

it('mail_bcc_address 未設定時は BCC なし', function () {
    $invoice = Invoice::factory()->create();

    expect((new InvoiceMail($invoice, 'x'))->envelope()->bcc)->toBe([]);
});
