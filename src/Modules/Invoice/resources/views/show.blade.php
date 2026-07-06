<x-invoice::layouts.master>
    <h1>請求書詳細: {{ $invoice->invoice_number }}</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <section aria-label="invoice-meta">
        <dl>
            <dt>顧客名</dt><dd>{{ $invoice->customer_name }}</dd>
            <dt>ステータス</dt><dd>{{ $invoice->status }}</dd>
            <dt>金額（税抜）</dt><dd>{{ number_format((int) $invoice->amount) }}</dd>
            <dt>消費税</dt><dd>{{ number_format((int) $invoice->tax_amount) }}</dd>
        </dl>
    </section>

    {{-- 状態・権限に応じた操作ボタン（詳細設計 §5.4 / §7 / FR-08 / FR-17） --}}
    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())
    @php($isFailed = in_array($invoice->status, [\Modules\Invoice\Models\Invoice::STATUS_FAILED, \Modules\Invoice\Models\Invoice::STATUS_FAILED_PERMANENT], true))
    <section aria-label="invoice-actions">
        {{-- 手動再送（general/admin） --}}
        <form method="POST" action="{{ route('invoices.resend', $invoice) }}">
            @csrf
            <button type="submit">手動再送</button>
        </form>

        {{-- PDF ダウンロード（general/admin） --}}
        <a href="{{ route('invoices.pdf', $invoice) }}">PDF ダウンロード</a>

        {{-- メールアドレス編集（admin のみ・failed/failed_permanent のみ） --}}
        @if ($isAdmin && $isFailed)
            <form method="POST" action="{{ route('invoices.emails', $invoice) }}">
                @csrf
                <label>送付先1 <input type="email" name="emails[]" value="{{ $invoice->customer_email }}"></label>
                <label>送付先2 <input type="email" name="emails[]" value="{{ $invoice->customer_email_2 }}"></label>
                <label>送付先3 <input type="email" name="emails[]" value="{{ $invoice->customer_email_3 }}"></label>
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
            @foreach ($invoice->items as $item)
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
            @forelse ($invoice->sendMailLogItems as $log)
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
</x-invoice::layouts.master>
