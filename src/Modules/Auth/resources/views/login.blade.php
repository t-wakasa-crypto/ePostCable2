<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ログイン</title>
</head>
<body>
    <main>
        <h1>ログイン</h1>

        @if ($errors->any())
            <div role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 全 POST に CSRF トークンを付与（NFR-S-05） --}}
        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div>
                <label for="email">メールアドレス</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div>
                <label for="password">パスワード</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="remember"> ログイン状態を保持する
                </label>
            </div>
            <button type="submit">ログイン</button>
        </form>
    </main>
</body>
</html>
