{{-- 出荷取得履歴（FA-01 / detailed-design §1A.7 / FR-11） --}}
<x-shipmentfetch::layouts.master title="出荷取得履歴">
    {{-- 上部：フィルタ・admin 限定バッチ手動起動 --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            {{-- ステータスフィルタ（allowlist・NFR-S-06） --}}
            <form method="GET" action="{{ route('shipment-fetch-logs.index') }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">ステータス</label>
                <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">すべて</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($currentStatus === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </form>

            {{-- バッチ手動起動（admin のみ表示・NFR-M-05） --}}
            @auth
                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('shipment-fetch-logs.runBatch') }}"
                        onsubmit="return confirm('出荷取得バッチを起動しますか？');">
                        @csrf
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">出荷取得バッチを起動</button>
                    </form>
                @endif
            @endauth
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">開始日時</th>
                        <th class="px-4 py-3">完了日時</th>
                        <th class="px-4 py-3">取得件数</th>
                        <th class="px-4 py-3">作成(納/請)</th>
                        <th class="px-4 py-3">スキップ</th>
                        <th class="px-4 py-3">実行秒</th>
                        <th class="px-4 py-3">エラー</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$log->status" /></td>
                            <td class="px-4 py-3 text-sm">{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm">{{ optional($log->completed_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $log->fetched_count }}</td>
                            <td class="px-4 py-3 text-sm">{{ $log->created_delivery_note_count }} / {{ $log->created_invoice_count }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $log->skipped_count }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $log->execution_seconds }}</td>
                            <td class="px-4 py-3 text-sm text-red-600">{{ $log->error_message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-3 text-sm text-gray-500">履歴がありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-shipmentfetch::layouts.master>
