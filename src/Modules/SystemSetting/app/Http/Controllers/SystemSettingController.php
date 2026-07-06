<?php

namespace Modules\SystemSetting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\SystemSetting\Mail\TestMail;
use Modules\SystemSetting\Models\SystemSetting;

/**
 * システム設定コントローラ（詳細設計 §1.4.7 / §4.6 / FR-13 / BR-06 / NFR-M-01/04）。admin 限定。
 *
 * integer 型は min_value〜max_value 範囲で検証、emails 型は1行1アドレスを
 * FILTER_VALIDATE_EMAIL で検証し改行区切りで保存する。テストメール送信も提供する。
 */
class SystemSettingController extends Controller
{
    /**
     * 設定一覧を表示する。
     */
    public function index(): View
    {
        $settings = SystemSetting::query()->orderBy('key')->get();

        return view('systemsetting::index', ['settings' => $settings]);
    }

    /**
     * 設定を更新する（integer は範囲検証・emails は改行区切り保存）。
     */
    public function update(Request $request): RedirectResponse
    {
        $input = $request->input('settings', []);
        $settings = SystemSetting::query()->get();

        foreach ($settings as $setting) {
            if (! array_key_exists($setting->key, $input)) {
                continue;
            }

            $raw = $input[$setting->key];

            if ($setting->type === SystemSetting::TYPE_INTEGER) {
                $value = $this->validateInteger($setting, $raw);
            } elseif ($setting->type === SystemSetting::TYPE_EMAILS) {
                $value = $this->validateEmails($setting, $raw);
            } else {
                $value = is_string($raw) ? $raw : (string) $raw;
            }

            // 変更値は次回ジョブ生成時に反映（ワーカー再起動不要・NFR-M-04）
            $setting->update(['value' => $value]);
        }

        return redirect()->route('system-settings.index')->with('status', '設定を更新しました。');
    }

    /**
     * テストメールを送信する（admin のみ・FR-13 / FR-14）。
     */
    public function sendTestMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Mail::to($validated['email'])->send(new TestMail);

        return back()->with('status', 'テストメールを送信しました。');
    }

    /**
     * integer 型の範囲検証（min_value〜max_value・BR-06）。
     */
    private function validateInteger(SystemSetting $setting, mixed $raw): string
    {
        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                'settings.'.$setting->key => $setting->key.' は整数で入力してください。',
            ]);
        }

        $value = (int) $raw;

        if (($setting->min_value !== null && $value < $setting->min_value)
            || ($setting->max_value !== null && $value > $setting->max_value)) {
            throw ValidationException::withMessages([
                'settings.'.$setting->key => $setting->key.' は '.$setting->min_value.'〜'.$setting->max_value.' の範囲で入力してください。',
            ]);
        }

        return (string) $value;
    }

    /**
     * emails 型の検証（1行1アドレス・FILTER_VALIDATE_EMAIL・改行区切り保存・BR-06）。
     */
    private function validateEmails(SystemSetting $setting, mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $valid = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (filter_var($line, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([
                    'settings.'.$setting->key => $setting->key.' に無効なメールアドレスが含まれています: '.$line,
                ]);
            }
            $valid[] = $line;
        }

        return $valid === [] ? null : implode("\n", $valid);
    }
}
