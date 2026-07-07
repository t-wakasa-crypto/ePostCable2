{{-- システム設定（admin・FA-01 / detailed-design §1A.9 / FR-13） --}}
<x-systemsetting::layouts.master title="システム設定">
    {{-- 設定フォーム --}}
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6 max-w-2xl">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">設定項目</h2>
        <form method="POST" action="{{ route('system-settings.update') }}" class="space-y-5">
            @csrf
            @foreach ($settings as $setting)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="setting-{{ $setting->key }}">
                        {{ $setting->key }}
                        <span class="text-xs text-gray-400">（{{ $setting->type }}）</span>
                    </label>
                    @if ($setting->description ?? false)
                        <p class="text-xs text-gray-500 mb-1">{{ $setting->description }}</p>
                    @endif

                    @if ($setting->type === 'emails')
                        {{-- emails 型：1 行 1 アドレス --}}
                        <textarea id="setting-{{ $setting->key }}" name="settings[{{ $setting->key }}]" rows="3"
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">{{ $setting->value }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">1 行 1 アドレスで入力してください。</p>
                    @elseif ($setting->type === 'integer')
                        {{-- integer 型：min/max 属性を min_value/max_value に対応 --}}
                        <input id="setting-{{ $setting->key }}" type="number" name="settings[{{ $setting->key }}]"
                            value="{{ $setting->value }}"
                            @if ($setting->min_value !== null) min="{{ $setting->min_value }}" @endif
                            @if ($setting->max_value !== null) max="{{ $setting->max_value }}" @endif
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                        @if ($setting->min_value !== null)
                            <p class="text-xs text-gray-500 mt-1">範囲: {{ $setting->min_value }}〜{{ $setting->max_value }}</p>
                        @endif
                    @else
                        <input id="setting-{{ $setting->key }}" type="text" name="settings[{{ $setting->key }}]"
                            value="{{ $setting->value }}"
                            class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                    @endif
                    @error('settings.' . $setting->key)<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">設定を保存</button>
        </form>
    </section>

    {{-- テストメール送信 --}}
    <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 max-w-2xl">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">テストメール送信</h2>
        <form method="POST" action="{{ route('system-settings.testMail') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-56">
                <label class="block text-sm font-medium text-gray-700 mb-1">宛先</label>
                <input type="email" name="email" required
                    class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-500 focus:border-indigo-500">
                @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">テストメールを送信</button>
        </form>
    </section>
</x-systemsetting::layouts.master>
