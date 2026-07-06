<x-invoice::layouts.master>
    <h1>請求書一覧</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())

    {{-- CSV ダウンロード（general/admin・現在のフィルタを引き継ぐ） --}}
    <a href="{{ route('invoices.csv', ['status' => $currentStatus]) }}">CSV ダウンロード</a>

    {{-- バッチ手動起動（admin のみ・詳細設計 §5.4 / §7） --}}
    @if ($isAdmin)
        <form method="POST" action="{{ route('invoices.runBatch') }}">
            @csrf
            <button type="submit">送信バッチを起動</button>
        </form>
    @endif

    {{-- ステータス別件数サマリー --}}
    <section aria-label="summary">
        <ul>
            @foreach ($statuses as $status)
                <li>{{ $status }}: {{ $summary[$status] ?? 0 }}</li>
            @endforeach
        </ul>
    </section>

    {{-- ステータスフィルタ（allowlist） --}}
    <form method="GET" action="{{ route('invoices.index') }}">
        <select name="status" onchange="this.form.submit()">
            <option value="">すべて</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected($currentStatus === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>請求書番号</th>
                <th>顧客名</th>
                <th>金額</th>
                <th>ステータス</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->customer_name }}</td>
                    <td>{{ number_format((int) $invoice->amount) }}</td>
                    <td>{{ $invoice->status }}</td>
                    <td><a href="{{ route('invoices.show', $invoice) }}">詳細</a></td>
                </tr>
            @empty
                <tr><td colspan="5">請求書がありません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $invoices->links() }}
</x-invoice::layouts.master>
