<x-sendmaillog::layouts.master>
    <h1>送信履歴詳細: {{ $log->batch_name }}</h1>

    <section aria-label="log-meta">
        <dl>
            <dt>バッチ種別</dt><dd>{{ $log->batch_key }}</dd>
            <dt>状態</dt><dd>{{ $log->displayStatus() }}</dd>
            <dt>開始日時</dt><dd>{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</dd>
            <dt>完了日時</dt><dd>{{ optional($log->completed_at)->format('Y-m-d H:i:s') }}</dd>
            <dt>投入件数</dt><dd>{{ $log->dispatched_count }}</dd>
            <dt>差し戻し件数</dt><dd>{{ $log->reset_count }}</dd>
        </dl>
    </section>

    <h2>送信明細</h2>
    <table>
        <thead>
            <tr><th>種別</th><th>対象</th><th>状態</th><th>送信日時</th><th>エラー</th></tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->sendable_type }}</td>
                    <td>{{ $item->sendable_id }}</td>
                    <td>{{ $item->status }}</td>
                    <td>{{ optional($item->sent_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $item->error_message }}</td>
                </tr>
            @empty
                <tr><td colspan="5">明細がありません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $items->links() }}
</x-sendmaillog::layouts.master>
