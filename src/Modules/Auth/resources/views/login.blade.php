<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ログイン - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- ログイン画面（FA-01 / detailed-design §1A.2 / FR-16）
     共通レイアウト非継承の単独ページ。中央寄せカードにログインフォームを縦積み配置。 --}}
<body class="min-h-screen flex items-center justify-center bg-gray-50 antialiased">
    <main class="w-full max-w-sm bg-white shadow rounded-lg p-8">
        <h1 class="text-xl font-semibold text-gray-800 text-center mb-6">{{ config('app.name', 'Laravel') }}</h1>

        {{-- バリデーション/認証エラー・試行制限メッセージ（フォーム上部） --}}
        @if ($errors->any())
            <div class="rounded p-3 mb-4 bg-red-50 text-red-800 border border-red-200" role="alert">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 全 POST に CSRF トークンを付与（NFR-S-05） --}}
        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">パスワード</label>
                <input id="password" type="password" name="password" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="remember" class="rounded border-gray-300"> ログイン状態を保持する
                </label>
            </div>
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">ログイン</button>
        </form>
    </main>
</body>
</html>
