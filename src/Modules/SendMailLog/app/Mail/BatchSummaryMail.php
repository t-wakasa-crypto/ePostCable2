<?php

namespace Modules\SendMailLog\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SystemSetting\Models\SystemSetting;

/**
 * バッチ完了通知メール（詳細設計 §1.5 / FR-14 / NFR-R-07）。
 *
 * 件名は「【バッチ完了】{batch_name}メール送信 {実行開始日時} 実行分」。添付なし・共通 BCC。
 * 送信有無（admin_notification_emails 未設定時は送らず警告ログのみ）の判定は Command 側の責務。
 */
class BatchSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SendMailLog $log,
    ) {}

    public function envelope(): Envelope
    {
        $startedAt = optional($this->log->started_at)->format('Y-m-d H:i');

        return new Envelope(
            subject: '【バッチ完了】'.$this->log->batch_name.'メール送信 '.$startedAt.' 実行分',
            bcc: SystemSetting::mailBccAddresses(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'sendmaillog::emails.batch_summary',
            with: ['log' => $this->log],
        );
    }
}
