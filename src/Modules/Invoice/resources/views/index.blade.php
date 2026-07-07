{{-- 請求書一覧（FA-01 / detailed-design §1A.4 / FR-08） --}}
<x-invoice::layouts.master title="請求書一覧">
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())

    {{-- 上部操作バー：サマリー・フィルタ・CSV・バッチ起動 --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        {{-- ステータス別件数サマリー --}}
        <section aria-label="summary" class="flex flex-wrap items-center gap-3 mb-4">
            @foreach ($statuses as $status)
                <span class="inline-flex items-center gap-2 text-sm">
                    <x-shared::status-badge :status="$status" />
                    <span class="font-semibold text-gray-800">{{ $summary[$status] ?? 0 }}</span>
                </span>
            @endforeach
        </section>

        <div class="flex flex-wrap items-end justify-between gap-4">
            {{-- ステータスフィルタ（allowlist） --}}
            <form method="GET" action="{{ route('invoices.index') }}">
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
                {{-- CSV ダウンロード（general/admin・現在のフィルタを引き継ぐ） --}}
                <a href="{{ route('invoices.csv', ['status' => $currentStatus]) }}"
                    class="bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">CSV ダウンロード</a>

                {{-- バッチ手動起動（admin のみ・詳細設計 §5.4 / §7） --}}
                @if ($isAdmin)
                    <form method="POST" action="{{ route('invoices.runBatch') }}"
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
        <form method="POST" action="{{ route('invoices.bulkRequeue') }}" id="bulk-requeue-form"
            onsubmit="return invoiceBulkConfirm();">
            @csrf
            @if ($isAdmin)
                <div class="flex items-center justify-end px-4 py-3 border-b border-gray-200">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">選択した請求書を再キュー</button>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            @if ($isAdmin)<th class="px-4 py-3">選択</th>@endif
                            <th class="px-4 py-3">請求書番号</th>
                            <th class="px-4 py-3">顧客名</th>
                            <th class="px-4 py-3">金額</th>
                            <th class="px-4 py-3">ステータス</th>
                            <th class="px-4 py-3">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($invoices as $invoice)
                            <tr class="hover:bg-gray-50">
                                @if ($isAdmin)
                                    <td class="px-4 py-3 text-sm">
                                        <input type="checkbox" name="ids[]" value="{{ $invoice->id }}"
                                            data-retry-count="{{ $invoice->retry_count }}" class="rounded border-gray-300">
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-sm">{{ $invoice->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm">{{ $invoice->customer_name }}</td>
                                <td class="px-4 py-3 text-sm text-right">{{ number_format((int) $invoice->amount) }}</td>
                                <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$invoice->status" /></td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">詳細</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('invoices.pdf', $invoice) }}" class="text-indigo-600 hover:underline">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-3 text-sm text-gray-500">請求書がありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>

    @if ($isAdmin)
        {{-- retry_count>=3 を含む一括再キューは確認ダイアログ（detailed-design §1A.4 / BR-07） --}}
        <script>
            function invoiceBulkConfirm() {
                var checked = document.querySelectorAll('#bulk-requeue-form input[name="ids[]"]:checked');
                if (checked.length === 0) {
                    alert('再キューする請求書を選択してください。');
                    return false;
                }
                for (var i = 0; i < checked.length; i++) {
                    if (parseInt(checked[i].dataset.retryCount || '0', 10) >= 3) {
                        return confirm('リトライ回数が3回以上の請求書が含まれています。再キューしますか？');
                    }
                }
                return true;
            }
        </script>
    @endif
</x-invoice::layouts.master>
