<?php

namespace Modules\DeliveryNote\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SystemSetting\Models\SystemSetting;

/**
 * 納品書メール（詳細設計 §1.5 / FR-14）。
 *
 * 件名は「【納品書】{delivery_number}」。PDF を添付し、全メール共通の BCC を付与する。
 */
class DeliveryNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  DeliveryNote  $deliveryNote  対象納品書
     * @param  string  $pdfContent  添付する PDF バイナリ
     */
    public function __construct(
        public DeliveryNote $deliveryNote,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【納品書】'.$this->deliveryNote->delivery_number,
            bcc: SystemSetting::mailBccAddresses(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'deliverynote::emails.delivery_note',
            with: ['deliveryNote' => $this->deliveryNote],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'delivery_'.$this->deliveryNote->delivery_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
