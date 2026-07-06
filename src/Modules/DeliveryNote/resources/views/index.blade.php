<x-deliverynote::layouts.master>
    <h1>納品書一覧</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @php($isAdmin = auth()->check() && auth()->user()->isAdmin())

    {{-- CSV ダウンロード（general/admin） --}}
    <a href="{{ route('delivery-notes.csv', ['status' => $currentStatus]) }}">CSV ダウンロード</a>

    {{-- バッチ手動起動（admin のみ） --}}
    @if ($isAdmin)
        <form method="POST" action="{{ route('delivery-notes.runBatch') }}">
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
    <form method="GET" action="{{ route('delivery-notes.index') }}">
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
                <th>納品書番号</th>
                <th>顧客名</th>
                <th>金額</th>
                <th>ステータス</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveryNotes as $note)
                <tr>
                    <td>{{ $note->delivery_number }}</td>
                    <td>{{ $note->customer_name }}</td>
                    <td>{{ number_format((int) $note->amount) }}</td>
                    <td>{{ $note->status }}</td>
                    <td><a href="{{ route('delivery-notes.show', $note) }}">詳細</a></td>
                </tr>
            @empty
                <tr><td colspan="5">納品書がありません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $deliveryNotes->links() }}
</x-deliverynote::layouts.master>
