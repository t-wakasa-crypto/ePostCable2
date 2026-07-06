<x-deliverynote::layouts.master>
    <h1>納品書詳細: {{ $deliveryNote->delivery_number }}</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <section aria-label="delivery-note-meta">
        <dl>
            <dt>顧客名</dt><dd>{{ $deliveryNote->customer_name }}</dd>
            <dt>ステータス</dt><dd>{{ $deliveryNote->status }}</dd>
            <dt>納品日</dt><dd>{{ optional($deliveryNote->delivery_date)->format('Y-m-d') }}</dd>
            <dt>金額（税抜）</dt><dd>{{ number_format((int) $deliveryNote->amount) }}</dd>
            <dt>消費税</dt><dd>{{ number_format((int) $deliveryNote->tax_amount) }}</dd>
        </dl>
    </section>

    {{-- 状態・権限に応じた操作ボタン（詳細設計 §5.4 / §7 / FR-09 / FR-17） --}}
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())
    @php($isFailed = in_array($deliveryNote->status, [\Modules\DeliveryNote\Models\DeliveryNote::STATUS_FAILED, \Modules\DeliveryNote\Models\DeliveryNote::STATUS_FAILED_PERMANENT], true))
    <section aria-label="delivery-note-actions">
        <form method="POST" action="{{ route('delivery-notes.resend', $deliveryNote) }}">
            @csrf
            <button type="submit">手動再送</button>
        </form>

        <a href="{{ route('delivery-notes.pdf', $deliveryNote) }}">PDF ダウンロード</a>

        @if ($isAdmin && $isFailed)
            <form method="POST" action="{{ route('delivery-notes.emails', $deliveryNote) }}">
                @csrf
                <label>送付先1 <input type="email" name="emails[]" value="{{ $deliveryNote->customer_email }}"></label>
                <label>送付先2 <input type="email" name="emails[]" value="{{ $deliveryNote->customer_email_2 }}"></label>
                <label>送付先3 <input type="email" name="emails[]" value="{{ $deliveryNote->customer_email_3 }}"></label>
                <button type="submit">メールアドレスを更新</button>
            </form>
        @endif
    </section>

    <h2>明細</h2>
    <table>
        <thead>
            <tr><th>品目</th><th>数量</th><th>単価</th><th>金額</th></tr>
        </thead>
        <tbody>
            @foreach ($deliveryNote->items as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td>{{ number_format((int) $item->quantity) }}</td>
                    <td>{{ number_format((int) $item->unit_price) }}</td>
                    <td>{{ number_format((int) $item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>メール送信履歴</h2>
    <table>
        <thead>
            <tr><th>ステータス</th><th>送信日時</th><th>エラー</th></tr>
        </thead>
        <tbody>
            @forelse ($deliveryNote->sendMailLogItems as $log)
                <tr>
                    <td>{{ $log->status }}</td>
                    <td>{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->error_message }}</td>
                </tr>
            @empty
                <tr><td colspan="3">送信履歴はありません。</td></tr>
            @endforelse
        </tbody>
    </table>
</x-deliverynote::layouts.master>
