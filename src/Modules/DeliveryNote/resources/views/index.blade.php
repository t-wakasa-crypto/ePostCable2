{{-- 納品書一覧（FA-01 / detailed-design §1A.5 / FR-09） --}}
<x-deliverynote::layouts.master title="納品書一覧">
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())

    {{-- 上部操作バー：サマリー・フィルタ・CSV・バッチ起動 --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <section aria-label="summary" class="flex flex-wrap items-center gap-3 mb-4">
            @foreach ($statuses as $status)
                <span class="inline-flex items-center gap-2 text-sm">
                    <x-shared::status-badge :status="$status" />
                    <span class="font-semibold text-gray-800">{{ $summary[$status] ?? 0 }}</span>
                </span>
            @endforeach
        </section>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <form method="GET" action="{{ route('delivery-notes.index') }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">ステータス</label>
                <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">すべて</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($currentStatus === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('delivery-notes.csv', ['status' => $currentStatus]) }}"
                    class="bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">CSV ダウンロード</a>

                @if ($isAdmin)
                    <form method="POST" action="{{ route('delivery-notes.runBatch') }}"
                        onsubmit="return confirm('送信バッチを起動しますか？');">
                        @csrf
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">送信バッチを起動</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- 一覧テーブル（bulkRequeue は admin のみチェックボックス選択） --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <form method="POST" action="{{ route('delivery-notes.bulkRequeue') }}" id="bulk-requeue-form"
            onsubmit="return deliveryNoteBulkConfirm();">
            @csrf
            @if ($isAdmin)
                <div class="flex items-center justify-end px-4 py-3 border-b border-gray-200">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">選択した納品書を再キュー</button>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            @if ($isAdmin)<th class="px-4 py-3">選択</th>@endif
                            <th class="px-4 py-3">納品書番号</th>
                            <th class="px-4 py-3">顧客名</th>
                            <th class="px-4 py-3">金額</th>
                            <th class="px-4 py-3">ステータス</th>
                            <th class="px-4 py-3">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($deliveryNotes as $note)
                            <tr class="hover:bg-gray-50">
                                @if ($isAdmin)
                                    <td class="px-4 py-3 text-sm">
                                        <input type="checkbox" name="ids[]" value="{{ $note->id }}"
                                            data-retry-count="{{ $note->retry_count }}" class="rounded border-gray-300">
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-sm">{{ $note->delivery_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $note->customer_name }}</td>
                                <td class="px-4 py-3 text-sm text-right">{{ number_format((int) $note->amount) }}</td>
                                <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$note->status" /></td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('delivery-notes.show', $note) }}" class="text-indigo-600 hover:underline">詳細</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('delivery-notes.pdf', $note) }}" class="text-indigo-600 hover:underline">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-3 text-sm text-gray-500">納品書がありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="mt-4">{{ $deliveryNotes->links() }}</div>

    @if ($isAdmin)
        <script>
            function deliveryNoteBulkConfirm() {
                var checked = document.querySelectorAll('#bulk-requeue-form input[name="ids[]"]:checked');
                if (checked.length === 0) {
                    alert('再キューする納品書を選択してください。');
                    return false;
                }
                for (var i = 0; i < checked.length; i++) {
                    if (parseInt(checked[i].dataset.retryCount || '0', 10) >= 3) {
                        return confirm('リトライ回数が3回以上の納品書が含まれています。再キューしますか？');
                    }
                }
                return true;
            }
        </script>
    @endif
</x-deliverynote::layouts.master>
