{{--
    共通管理画面レイアウト（FA-01 / basic-design §5A.2 / detailed-design §1A.1）
    サイドナビ（左固定）＋ ヘッダー（上部）＋ メインコンテンツ の 2 カラム構成。
    各モジュールの layouts.master がこのコンポーネントへ委譲することで、
    ナビ項目・ヘッダーの変更が全画面へ一括反映される（NFR-M）。

    - $title: ヘッダーに表示する画面タイトル（未指定時はアプリ名）。
    - admin 専用リンク（ユーザー管理・システム設定）は isAdmin() 時のみ表示（FR-17）。
      表示制御は UI 補助であり、保護の本体は auth / admin ミドルウェア（§7）。
--}}
@props(['title' => null])

@php
    $user = auth()->user();
    $isAdmin = $user && $user->isAdmin();

    // サイドナビ項目（表示順は detailed-design §1A.1 と整合）
    $navItems = [
        ['label' => 'ダッシュボード', 'route' => 'dashboard', 'active' => 'dashboard', 'admin' => false],
        ['label' => '請求書', 'route' => 'invoices.index', 'active' => 'invoices.*', 'admin' => false],
        ['label' => '納品書', 'route' => 'delivery-notes.index', 'active' => 'delivery-notes.*', 'admin' => false],
        ['label' => 'メール送信履歴', 'route' => 'send-mail-logs.index', 'active' => 'send-mail-logs.*', 'admin' => false],
        ['label' => '出荷取得履歴', 'route' => 'shipment-fetch-logs.index', 'active' => 'shipment-fetch-logs.*', 'admin' => false],
        ['label' => 'ユーザー管理', 'route' => 'users.index', 'active' => 'users.*', 'admin' => true],
        ['label' => 'システム設定', 'route' => 'system-settings.index', 'active' => 'system-settings.*', 'admin' => true],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="flex min-h-screen">
        {{-- サイドナビ（左固定） --}}
        <aside class="w-64 shrink-0 bg-slate-800 text-slate-100 min-h-screen">
            <div class="h-14 flex items-center px-6 border-b border-slate-700">
                <span class="text-lg font-semibold">{{ config('app.name', 'Laravel') }}</span>
            </div>
            <nav class="p-3 space-y-1">
                @foreach ($navItems as $item)
                    @continue($item['admin'] && ! $isAdmin)
                    @php($isActive = request()->routeIs($item['active']))
                    <a href="{{ route($item['route']) }}"
                        class="block px-4 py-2 rounded text-sm {{ $isActive ? 'bg-slate-900 font-semibold' : 'hover:bg-slate-700' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- 右カラム：ヘッダー＋メイン --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- ヘッダー（上部） --}}
            <header class="flex items-center justify-between h-14 px-6 bg-white border-b border-gray-200">
                <h1 class="text-lg font-semibold text-gray-800">{{ $title ?? config('app.name', 'Laravel') }}</h1>

                @auth
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-700">{{ $user->name }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $isAdmin ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $isAdmin ? 'admin' : 'general' }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="bg-white border border-gray-300 hover:bg-gray-50 text-sm px-3 py-1.5 rounded">
                                ログアウト
                            </button>
                        </form>
                    </div>
                @endauth
            </header>

            {{-- メインコンテンツ --}}
            <main class="flex-1 bg-gray-50 p-6">
                {{-- フラッシュメッセージ領域（最上部） --}}
                @if (session('status'))
                    <div class="rounded p-3 mb-4 bg-green-50 text-green-800 border border-green-200" role="status">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="rounded p-3 mb-4 bg-red-50 text-red-800 border border-red-200" role="alert">
                        <ul class="list-disc list-inside space-y-1 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>

</html>
