<?php

/**
 * DeliveryNoteMail の件名・添付・BCC を検証するテスト（T071 / 詳細設計 §1.5 / FR-14）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Modules\DeliveryNote\Mail\DeliveryNoteMail;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('件名は【納品書】{delivery_number} 形式', function () {
    $note = DeliveryNote::factory()->create(['delivery_number' => 'DN-555']);

    expect((new DeliveryNoteMail($note, '%PDF-dummy'))->envelope()->subject)->toBe('【納品書】DN-555');
});

it('PDF を添付する', function () {
    $note = DeliveryNote::factory()->create();
    $mail = new DeliveryNoteMail($note, '%PDF-dummy');

    $mail->assertHasAttachment(
        Attachment::fromData(fn () => '%PDF-dummy', 'delivery_'.$note->delivery_number.'.pdf')
            ->withMime('application/pdf')
    );
});

it('共通 BCC を付与する', function () {
    SystemSetting::create(['key' => 'mail_bcc_address', 'value' => 'bcc@example.com', 'type' => 'emails']);
    $note = DeliveryNote::factory()->create();

    $bcc = collect((new DeliveryNoteMail($note, 'x'))->envelope()->bcc)->pluck('address')->all();

    expect($bcc)->toBe(['bcc@example.com']);
});
