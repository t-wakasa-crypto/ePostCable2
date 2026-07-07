{{--
    共通 状態バッジ（FA-01 / detailed-design §1A.1 部品表）
    書類・ログの status / displayStatus を色分けして表示する再利用部品。
    使用例: <x-shared::status-badge :status="$invoice->status" />

    配色（detailed-design §1A.1）:
      pending          … 灰
      processing       … 青
      sent / completed … 緑
      failed           … 赤
      failed_permanent … 濃赤
      running          … 黄
      それ以外          … 灰（既定）
--}}
@props(['status' => null])

@php
    $key = (string) $status;
    $map = [
        'pending' => 'bg-gray-100 text-gray-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'sent' => 'bg-green-100 text-green-700',
        'completed' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
        'failed_permanent' => 'bg-red-200 text-red-900',
        'running' => 'bg-yellow-100 text-yellow-700',
    ];
    $classes = $map[$key] ?? 'bg-gray-100 text-gray-700';
@endphp

<span class="inline-block text-xs px-2 py-0.5 rounded-full {{ $classes }}">{{ $status }}</span>
