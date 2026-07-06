<x-user::layouts.master>
    <h1>ユーザー管理</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- role フィルタ・退職者表示切替（allowlist・NFR-S-06） --}}
    <form method="GET" action="{{ route('users.index') }}">
        <select name="role">
            <option value="">全ロール</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected($currentRole === $role)>{{ $role }}</option>
            @endforeach
        </select>
        <label><input type="checkbox" name="include_retired" value="1" @checked($includeRetired)> 退職者を含む</label>
        <button type="submit">絞り込み</button>
    </form>

    <table>
        <thead>
            <tr><th>ID</th><th>名前</th><th>メール</th><th>ロール</th><th>退職</th><th>操作</th></tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role }}</td>
                    <td>{{ $user->isRetired() ? '退職' : '在籍' }}</td>
                    <td>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">ユーザーがいません。</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $users->links() }}

    <h2>新規ユーザー作成</h2>
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div><label>名前 <input type="text" name="name" value="{{ old('name') }}" required></label></div>
        <div><label>メール <input type="email" name="email" value="{{ old('email') }}" required></label></div>
        <div><label>パスワード <input type="password" name="password" required></label></div>
        <div><label>パスワード（確認） <input type="password" name="password_confirmation" required></label></div>
        <div>
            <label>ロール
                <select name="role">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <button type="submit">作成</button>
    </form>
</x-user::layouts.master>
