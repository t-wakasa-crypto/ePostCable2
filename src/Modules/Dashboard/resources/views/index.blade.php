<x-dashboard::layouts.master>
    <h1>ダッシュボード</h1>

    <section aria-label="invoice-summary">
        <h2>請求書ステータス別件数</h2>
        <ul>
            @foreach ($invoiceSummary as $status => $count)
                <li>{{ $status }}: {{ $count }}</li>
            @endforeach
        </ul>
    </section>

    <section aria-label="delivery-note-summary">
        <h2>納品書ステータス別件数</h2>
        <ul>
            @foreach ($deliveryNoteSummary as $status => $count)
                <li>{{ $status }}: {{ $count }}</li>
            @endforeach
        </ul>
    </section>

    <section aria-label="send-mail-log-summary">
        <h2>メール送信履歴サマリー（失敗ログ・手動再送は除外）</h2>
        <ul>
            @foreach ($sendMailLogSummary as $display => $count)
                <li>{{ $display }}: {{ $count }}</li>
            @endforeach
        </ul>

        <h3>直近の送信バッチ</h3>
        <table>
            <thead><tr><th>バッチ</th><th>開始</th><th>状態</th><th>投入件数</th></tr></thead>
            <tbody>
                @forelse ($recentSendMailLogs as $log)
                    <tr>
                        <td>{{ $log->batch_name }}</td>
                        <td>{{ optional($log->started_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->displayStatus() }}</td>
                        <td>{{ $log->dispatched_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">履歴はありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section aria-label="last-batches">
        <h2>各送信バッチ最終実行</h2>
        <dl>
            <dt>請求書送信バッチ</dt>
            <dd>{{ $lastInvoiceBatch ? optional($lastInvoiceBatch->started_at)->format('Y-m-d H:i').'（'.$lastInvoiceBatch->displayStatus().'）' : '未実行' }}</dd>
            <dt>納品書送信バッチ</dt>
            <dd>{{ $lastDeliveryNoteBatch ? optional($lastDeliveryNoteBatch->started_at)->format('Y-m-d H:i').'（'.$lastDeliveryNoteBatch->displayStatus().'）' : '未実行' }}</dd>
        </dl>
    </section>

    <section aria-label="fetch-logs">
        <h2>出荷取得バッチ直近実行</h2>
        <table>
            <thead><tr><th>状態</th><th>開始</th><th>取得</th><th>作成(納/請)</th><th>スキップ</th></tr></thead>
            <tbody>
                @forelse ($recentFetchLogs as $fetch)
                    <tr>
                        <td>{{ $fetch->status }}</td>
                        <td>{{ optional($fetch->started_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $fetch->fetched_count }}</td>
                        <td>{{ $fetch->created_delivery_note_count }} / {{ $fetch->created_invoice_count }}</td>
                        <td>{{ $fetch->skipped_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">履歴はありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-dashboard::layouts.master>
