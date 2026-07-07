{{-- 請求書詳細（FA-01 / detailed-design §1A.4 / FR-08 / FR-15） --}}
<x-invoice::layouts.master :title="'請求書詳細: ' . $invoice->invoice_number">
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())
    @php($isFailed = in_array($invoice->status, [\Modules\Invoice\Models\Invoice::STATUS_FAILED, \Modules\Invoice\Models\Invoice::STATUS_FAILED_PERMANENT], true))

    {{-- 書類ヘッダーカード --}}
    <section aria-label="invoice-meta" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">{{ $invoice->invoice_number }}</h2>
            <x-shared::status-badge :status="$invoice->status" />
        </div>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">顧客名</dt><dd class="text-gray-800">{{ $invoice->customer_name }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">ステータス</dt><dd class="text-gray-800">{{ $invoice->status }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">金額（税抜）</dt><dd class="text-gray-800">{{ number_format((int) $invoice->amount) }}</dd></div>
            <div class="flex justify-between border-b border-gray-100 pb-2"><dt class="text-gray-500">消費税</dt><dd class="text-gray-800">{{ number_format((int) $invoice->tax_amount) }}</dd></div>
        </dl>
    </section>

    {{-- 操作カード（状態・権限に応じた操作ボタン・詳細設計 §5.4 / §7 / FR-08 / FR-17） --}}
    <section aria-label="invoice-actions" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-500 mb-3">操作</h3>
        <div class="flex flex-wrap items-center gap-3">
            {{-- 手動再送（general/admin） --}}
            <form method="POST" action="{{ route('invoices.resend', $invoice) }}"
                onsubmit="return confirm('この請求書を再送しますか？');">
                @csrf
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">手動再送</button>
            </form>

            {{-- PDF ダウンロード（general/admin） --}}
            <a href="{{ route('invoices.pdf', $invoice) }}"
                class="bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">PDF ダウンロード</a>
        </div>

        {{-- メールアドレス編集（admin のみ・failed/failed_permanent のみ） --}}
        @if ($isAdmin && $isFailed)
            <form method="POST" action="{{ route('invoices.emails', $invoice) }}" class="mt-6 border-t border-gray-100 pt-4 space-y-3">
                @csrf
                <h4 class="text-sm font-medium text-gray-700">送付先メールアドレス（1〜3件）</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">送付先1</label>
                        <input type="email" name="emails[]" value="{{ $invoice->customer_email }}"
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">送付先2</label>
                        <input type="email" name="emails[]" value="{{ $invoice->customer_email_2 }}"
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">送付先3</label>
                        <input type="email" name="emails[]" value="{{ $invoice->customer_email_3 }}"
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">メールアドレスを更新</button>
            </form>
        @endif
    </section>

    {{-- 明細 --}}
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">明細</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr><th class="px-4 py-3">品目</th><th class="px-4 py-3">数量</th><th class="px-4 py-3">単価</th><th class="px-4 py-3">金額</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoice->items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ $item->item_name }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((int) $item->quantity) }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((int) $item->unit_price) }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((int) $item->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- メール送信履歴 --}}
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">メール送信履歴</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr><th class="px-4 py-3">ステータス</th><th class="px-4 py-3">送信日時</th><th class="px-4 py-3">エラー</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoice->sendMailLogItems as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$log->status" /></td>
                            <td class="px-4 py-3 text-sm">{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 text-sm text-red-600">{{ $log->error_message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-3 text-sm text-gray-500">送信履歴はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-invoice::layouts.master>
