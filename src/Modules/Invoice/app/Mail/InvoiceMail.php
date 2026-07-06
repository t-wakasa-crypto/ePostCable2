<?php

namespace Modules\Invoice\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Invoice\Models\Invoice;
use Modules\SystemSetting\Models\SystemSetting;

/**
 * 請求書メール（詳細設計 §1.5 / FR-14）。
 *
 * 件名は「【請求書】{invoice_number}」。PDF を添付し、全メール共通の BCC
 * （SystemSetting::mailBccAddresses()）を envelope で付与する（未設定時なし）。
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Invoice  $invoice  対象請求書
     * @param  string  $pdfContent  添付する PDF バイナリ
     */
    public function __construct(
        public Invoice $invoice,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【請求書】'.$this->invoice->invoice_number,
            bcc: SystemSetting::mailBccAddresses(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'invoice::emails.invoice',
            with: ['invoice' => $this->invoice],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'invoice_'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
