<x-sendmaillog::layouts.master>
    <h1>メール送信履歴</h1>

    {{-- フィルタ（allowlist・NFR-S-06） --}}
    <form method="GET" action="{{ route('send-mail-logs.index') }}">
        <select name="filter" onchange="this.form.submit()">
            <option value="">通常一覧（失敗・手動再送を除く）</option>
            @foreach ($filters as $filter)
                <option value="{{ $filter }}" @selected($currentFilter === $filter)>{{ $filter }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>バッチ</th>
                <th>開始日時</th>
                <th>状態</th>
                <th>投入件数</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->batch_name }}</td>
                    <td>{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->displayStatus() }}</td>
                    <td>{{ $log->dispatched_count }}</td>
                    <td><a href="{{ route('send-mail-logs.show', $log) }}">詳細</a></td>
                </tr>
            @empty
                <tr><td colspan="5">送信履歴がありません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $logs->links() }}
</x-sendmaillog::layouts.master>
