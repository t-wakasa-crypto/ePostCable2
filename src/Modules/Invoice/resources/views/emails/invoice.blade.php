{{-- 請求書メール本文（FR-14） --}}
<p>{{ $invoice->customer_name }} 御中</p>

<p>いつもお世話になっております。<br>
請求書（請求書番号: {{ $invoice->invoice_number }}）を送付いたします。</p>

<p>添付の PDF をご確認ください。</p>

<p>何卒よろしくお願い申し上げます。</p>
