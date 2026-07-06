{{-- バッチ完了通知メール本文（FR-14 / NFR-R-07） --}}
<p>{{ $log->batch_name }}メール送信バッチが完了しました。</p>

<ul>
    <li>実行開始: {{ optional($log->started_at)->format('Y-m-d H:i:s') }}</li>
    <li>完了日時: {{ optional($log->completed_at)->format('Y-m-d H:i:s') }}</li>
    <li>投入件数: {{ $log->dispatched_count }}</li>
    <li>差し戻し件数（stuck）: {{ $log->reset_count }}</li>
    <li>再送差し戻し件数: {{ $log->retry_failed_count }}</li>
    <li>実行秒数: {{ $log->execution_seconds }}</li>
</ul>
