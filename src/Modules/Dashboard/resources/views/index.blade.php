{{-- ダッシュボード（FA-01 / detailed-design §1A.3 / FR-07） --}}
<x-dashboard::layouts.master title="ダッシュボード">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- 請求書ステータス別件数 --}}
        <section aria-label="invoice-summary" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-3">請求書ステータス別件数</h2>
            <ul class="space-y-2">
                @foreach ($invoiceSummary as $status => $count)
                    <li class="flex items-center justify-between">
                        <x-shared::status-badge :status="$status" />
                        <span class="text-2xl font-bold text-gray-800">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('invoices.index') }}"
                class="inline-block mt-4 bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">請求書一覧へ</a>
        </section>

        {{-- 納品書ステータス別件数 --}}
        <section aria-label="delivery-note-summary" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-3">納品書ステータス別件数</h2>
            <ul class="space-y-2">
                @foreach ($deliveryNoteSummary as $status => $count)
                    <li class="flex items-center justify-between">
                        <x-shared::status-badge :status="$status" />
                        <span class="text-2xl font-bold text-gray-800">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('delivery-notes.index') }}"
                class="inline-block mt-4 bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">納品書一覧へ</a>
        </section>

        {{-- メール送信履歴サマリー（失敗ログ・手動再送は除外） --}}
        <section aria-label="send-mail-log-summary" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-3">メール送信履歴サマリー（失敗ログ・手動再送は除外）</h2>
            <ul class="space-y-2">
                @foreach ($sendMailLogSummary as $display => $count)
                    <li class="text-gray-800">
                        <span class="text-sm">{{ $display }}: {{ $count }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('send-mail-logs.index') }}"
                class="inline-block mt-4 bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm">送信履歴一覧へ</a>
        </section>
    </div>

    {{-- 直近の送信バッチ --}}
    <section aria-label="recent-send-mail-logs" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mt-6">
        <h3 class="text-sm font-medium text-gray-500 mb-3">直近の送信バッチ</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">バッチ</th>
                        <th class="px-4 py-3">開始</th>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">投入件数</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentSendMailLogs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ $log->batch_name }}</td>
                            <td class="px-4 py-3 text-sm">{{ optional($log->started_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$log->displayStatus()" /></td>
                            <td class="px-4 py-3 text-sm">{{ $log->dispatched_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-3 text-sm text-gray-500">履歴はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 各送信バッチ最終実行 --}}
    <section aria-label="last-batches" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mt-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">各送信バッチ最終実行</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">請求書送信バッチ</dt>
                <dd class="text-gray-800 mt-1">{{ $lastInvoiceBatch ? optional($lastInvoiceBatch->started_at)->format('Y-m-d H:i').'（'.$lastInvoiceBatch->displayStatus().'）' : '未実行' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">納品書送信バッチ</dt>
                <dd class="text-gray-800 mt-1">{{ $lastDeliveryNoteBatch ? optional($lastDeliveryNoteBatch->started_at)->format('Y-m-d H:i').'（'.$lastDeliveryNoteBatch->displayStatus().'）' : '未実行' }}</dd>
            </div>
        </dl>
    </section>

    {{-- 出荷取得バッチ直近実行 --}}
    <section aria-label="fetch-logs" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mt-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">出荷取得バッチ直近実行</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">開始</th>
                        <th class="px-4 py-3">取得</th>
                        <th class="px-4 py-3">作成(納/請)</th>
                        <th class="px-4 py-3">スキップ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentFetchLogs as $fetch)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm"><x-shared::status-badge :status="$fetch->status" /></td>
                            <td class="px-4 py-3 text-sm">{{ optional($fetch->started_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fetch->fetched_count }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fetch->created_delivery_note_count }} / {{ $fetch->created_invoice_count }}</td>
                            <td class="px-4 py-3 text-sm">{{ $fetch->skipped_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-3 text-sm text-gray-500">履歴はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-dashboard::layouts.master>
