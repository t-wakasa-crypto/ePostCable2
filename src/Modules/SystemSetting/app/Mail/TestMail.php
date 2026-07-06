<?php

namespace Modules\SystemSetting\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\SystemSetting\Models\SystemSetting;

/**
 * テストメール（詳細設計 §1.5 / FR-14）。
 *
 * 件名は「【テスト】メール送信テスト」。添付なし・共通 BCC。
 * システム設定画面から任意アドレス宛に送信し、SMTP 設定の疎通確認に用いる。
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【テスト】メール送信テスト',
            bcc: SystemSetting::mailBccAddresses(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'systemsetting::emails.test',
        );
    }
}
