{{-- 納品書 PDF テンプレート（詳細設計 §1.3.2 / FR-15）。A4 縦・日本語表示。 --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'IPAexGothic', sans-serif; font-size: 12px; color: #000; }
        h1 { font-size: 20px; text-align: center; margin-bottom: 20px; }
        .meta { margin-bottom: 16px; }
        .meta td { padding: 2px 8px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #333; padding: 6px; }
        table.items th { background: #eee; }
        .num { text-align: right; }
        .totals { margin-top: 16px; width: 100%; }
        .totals td { padding: 4px 8px; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; width: 160px; }
    </style>
</head>
<body>
    <h1>納 品 書</h1>

    <table class="meta">
        <tr><td>納品書番号</td><td>{{ $deliveryNote->delivery_number }}</td></tr>
        <tr><td>顧客名</td><td>{{ $deliveryNote->customer_name }} 御中</td></tr>
        <tr><td>納品日</td><td>{{ optional($deliveryNote->delivery_date)->format('Y年m月d日') }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>品目</th>
                <th>数量</th>
                <th>単価</th>
                <th>金額</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryNote->items as $item)
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td class="num">{{ number_format((int) $item->quantity) }}</td>
                    <td class="num">{{ number_format((int) $item->unit_price) }}</td>
                    <td class="num">{{ number_format((int) $item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">小計（税抜）</td>
            <td class="value">{{ number_format((int) $deliveryNote->amount) }} 円</td>
        </tr>
        <tr>
            <td class="label">消費税（{{ (int) $deliveryNote->tax }}%）</td>
            <td class="value">{{ number_format((int) $deliveryNote->tax_amount) }} 円</td>
        </tr>
        <tr>
            <td class="label"><strong>合計（税込）</strong></td>
            <td class="value"><strong>{{ number_format((int) $deliveryNote->amount + (int) $deliveryNote->tax_amount) }} 円</strong></td>
        </tr>
    </table>
</body>
</html>
