<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ログイン・ログアウトを扱うコントローラ（詳細設計 §1.4.8 / §3.5 / FR-16 / NFR-S）。
 *
 * メール＋パスワード認証、退職者ログイン拒否、試行回数制限（メール+IP・5回・
 * 約60秒ロック・成功でリセット）を実装する。
 */
class LoginController extends Controller
{
    /** ロックまでの最大失敗回数（NFR-S-03） */
    private const MAX_ATTEMPTS = 5;

    /** ロック秒数（NFR-S-03） */
    private const DECAY_SECONDS = 60;

    /**
     * ログイン画面を表示する。
     */
    public function showLoginForm(): View
    {
        return view('auth::login');
    }

    /**
     * ログイン処理。
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = $this->throttleKey($request);

        // ① 試行回数制限（メール+IP・NFR-S-03）
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('ログイン試行回数が上限に達しました。約:seconds 秒後に再度お試しください。', ['seconds' => $seconds]),
            ]);
        }

        // ② 認証（退職者は認証情報が正しくてもログイン拒否・NFR-S-04）
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('メールアドレスまたはパスワードが正しくありません。'),
            ]);
        }

        // ③ 退職者判定（isRetired）
        if (Auth::user()->isRetired()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('このアカウントは無効化されています。'),
            ]);
        }

        // ④ 成功でカウンタリセット・セッション固定化攻撃対策
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * ログアウト処理。
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * 試行回数制限のキー（メール+IP 単位・NFR-S-03）。
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
    }
}
