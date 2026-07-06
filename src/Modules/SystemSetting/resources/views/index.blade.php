<x-systemsetting::layouts.master>
    <h1>システム設定</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('system-settings.update') }}">
        @csrf
        <table>
            <thead><tr><th>キー</th><th>型</th><th>値</th><th>範囲</th></tr></thead>
            <tbody>
                @foreach ($settings as $setting)
                    <tr>
                        <td>{{ $setting->key }}</td>
                        <td>{{ $setting->type }}</td>
                        <td>
                            @if ($setting->type === 'emails')
                                <textarea name="settings[{{ $setting->key }}]" rows="3">{{ $setting->value }}</textarea>
                            @else
                                <input type="text" name="settings[{{ $setting->key }}]" value="{{ $setting->value }}">
                            @endif
                        </td>
                        <td>{{ $setting->min_value !== null ? $setting->min_value.'〜'.$setting->max_value : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="submit">設定を保存</button>
    </form>

    <h2>テストメール送信</h2>
    <form method="POST" action="{{ route('system-settings.testMail') }}">
        @csrf
        <label>宛先 <input type="email" name="email" required></label>
        <button type="submit">テストメールを送信</button>
    </form>
</x-systemsetting::layouts.master>
