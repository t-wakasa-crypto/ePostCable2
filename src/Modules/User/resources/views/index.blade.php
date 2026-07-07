{{-- ユーザー管理（admin・FA-01 / detailed-design §1A.8 / FR-12 / BR-08） --}}
<x-user::layouts.master title="ユーザー管理">
    {{-- 上部：新規作成へのスクロール導線・role フィルタ・退職者表示切替 --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            {{-- role フィルタ・退職者表示切替（allowlist・NFR-S-06） --}}
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ロール</label>
                    <select name="role"
                        class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">全ロール</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected($currentRole === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 mb-2">
                    <input type="checkbox" name="include_retired" value="1" @checked($includeRetired) class="rounded border-gray-300"> 退職者を含む
                </label>
                <button type="submit"
                    class="bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded text-sm mb-1">絞り込み</button>
            </form>

            <a href="#new-user"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">新規ユーザー作成</a>
        </div>
    </div>

    {{-- ユーザー一覧 --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">名前</th>
                        <th class="px-4 py-3">メール</th>
                        <th class="px-4 py-3">ロール</th>
                        <th class="px-4 py-3">退職</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">{{ $user->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-block text-xs px-2 py-0.5 rounded-full {{ $user->role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ $user->role }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($user->isRetired())
                                    <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">退職</span>
                                @else
                                    <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">在籍</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    {{-- 行内編集フォームを開閉（inline 展開） --}}
                                    <details class="inline">
                                        <summary
                                            class="cursor-pointer list-none bg-white border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded text-sm inline-block">編集</summary>
                                        {{-- 編集フォーム（name/email/password 任意/role/retired・BR-08。自己編集も許可） --}}
                                        <form method="POST" action="{{ route('users.update', $user) }}"
                                            class="mt-3 space-y-3 bg-gray-50 border border-gray-200 rounded p-4 text-left w-72">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">名前</label>
                                                <input type="text" name="name" value="{{ $user->name }}" required
                                                    class="border border-gray-300 rounded px-2 py-1.5 w-full focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">メール</label>
                                                <input type="email" name="email" value="{{ $user->email }}" required
                                                    class="border border-gray-300 rounded px-2 py-1.5 w-full focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">パスワード（変更時のみ）</label>
                                                <input type="password" name="password" autocomplete="new-password"
                                                    class="border border-gray-300 rounded px-2 py-1.5 w-full focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">パスワード（確認）</label>
                                                <input type="password" name="password_confirmation" autocomplete="new-password"
                                                    class="border border-gray-300 rounded px-2 py-1.5 w-full focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">ロール</label>
                                                <select name="role"
                                                    class="border border-gray-300 rounded px-2 py-1.5 w-full focus:ring-indigo-500 focus:border-indigo-500">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <label class="flex items-center gap-2 text-xs text-gray-700">
                                                <input type="hidden" name="retired" value="0">
                                                <input type="checkbox" name="retired" value="1" @checked($user->isRetired())
                                                    class="rounded border-gray-300"> 退職扱いにする
                                            </label>
                                            <button type="submit"
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-sm">更新</button>
                                        </form>
                                    </details>

                                    {{-- 自分自身の削除ボタンは非表示（BR-08） --}}
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                            onsubmit="return confirm('削除しますか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-sm">削除</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">（ログイン中）</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-3 text-sm text-gray-500">ユーザーがいません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    {{-- 新規ユーザー作成フォーム --}}
    <section id="new-user" class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mt-6 max-w-lg">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">新規ユーザー作成</h2>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">名前</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">メール</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">パスワード</label>
                <input type="password" name="password" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                @error('password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">パスワード（確認）</label>
                <input type="password" name="password_confirmation" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ロール</label>
                <select name="role"
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
                @error('role')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">作成</button>
        </form>
    </section>
</x-user::layouts.master>
