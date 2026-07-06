<x-shipmentfetch::layouts.master>
    <h1>出荷取得履歴</h1>

    {{-- ステータスフィルタ（allowlist・NFR-S-06） --}}
    <form method="GET" action="{{ route('shipment-fetch-logs.index') }}">
        <select name="status" onchange="this.form.submit()">
            <option value="">すべて</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected($currentStatus === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </form>

    {{-- バッチ手動起動（admin のみ表示・NFR-M-05） --}}
    @auth
        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('shipment-fetch-logs.runBatch') }}">
                @csrf
                <button type="submit">出荷取得バッチを起動</button>
            </form>
        @endif
    @endauth

    <table>
        <thead>
            <tr>
                <th>状態</th>
                <th>開始日時</th>
                <th>取得件数</th>
                <th>作成(納/請)</th>
                <th>スキップ</th>
                <th>実行秒</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->status }}</td>
                    <td>{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->fetched_count }}</td>
                    <td>{{ $log->created_delivery_note_count }} / {{ $log->created_invoice_count }}</td>
                    <td>{{ $log->skipped_count }}</td>
                    <td>{{ $log->execution_seconds }}</td>
                </tr>
            @empty
                <tr><td colspan="6">履歴がありません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $logs->links() }}
</x-shipmentfetch::layouts.master>
