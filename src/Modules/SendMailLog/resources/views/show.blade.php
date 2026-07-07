{{-- メール送信履歴 詳細（FA-01 / detailed-design §1A.6 / FR-10） --}}
<x-sendmaillog::layouts.master :title="'送信履歴詳細: ' . $log->batch_name">
    {{-- バッチ実行情報カード --}}
    <section aria-label="log-meta" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">{{ $log->batch_name }}</h2>
            <x-shared::status-badge :status="$log->displayStatus()" />
        </div>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">バッチ種別</dt><dd class="text-gray-800">{{ $log->batch_key }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">状態</dt><dd class="text-gray-800">{{ $log->displayStatus() }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">開始日時</dt><dd class="text-gray-800">{{ optional($log->started_at)->format('Y-m-d H:i:s') }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">完了日時</dt><dd class="text-gray-800">{{ optional($log->completed_at)->format('Y-m-d H:i:s') }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">投入件数</dt><dd class="text-gray-800">{{ $log->dispatched_count }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">差し戻し件数</dt><dd class="text-gray-800">{{ $log->reset_count }}</dd></div>
        </dl>
    </section>

    {{-- 送信明細 --}}
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">送信明細</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">種別</th>
                        <th class="px-4 py-3">対象</th>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">送信日時</th>
                        <th class="px-4 py-3">エラー</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ $item->sendable_type }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->sendable_id }}</td>
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$item->status" /></td>
                            <td class="px-4 py-3 text-sm">{{ optional($item->sent_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm text-red-600">{{ $item->error_message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-3 text-sm text-gray-500">明細がありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $items->links() }}</div>
</x-sendmaillog::layouts.master>
