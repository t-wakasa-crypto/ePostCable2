{{-- メール送信履歴 一覧（FA-01 / detailed-design §1A.6 / FR-10） --}}
<x-sendmaillog::layouts.master title="メール送信履歴">
    {{-- フィルタ（allowlist・NFR-S-06） --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('send-mail-logs.index') }}">
            <label class="block text-sm font-medium text-gray-700 mb-1">フィルタ</label>
            <select name="filter" onchange="this.form.submit()"
                class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">通常一覧（失敗・手動再送を除く）</option>
                @foreach ($filters as $filter)
                    <option value="{{ $filter }}" @selected($currentFilter === $filter)>{{ $filter }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">バッチ</th>
                        <th class="px-4 py-3">開始日時</th>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">投入件数</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ $log->batch_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$log->displayStatus()" /></td>
                            <td class="px-4 py-3 text-sm text-right">{{ $log->dispatched_count }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('send-mail-logs.show', $log) }}" class="text-indigo-600 hover:underline">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-3 text-sm text-gray-500">送信履歴がありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-sendmaillog::layouts.master>
