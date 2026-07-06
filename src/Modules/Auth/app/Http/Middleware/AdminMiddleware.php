<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者専用ルートを保護するミドルウェア（詳細設計 §7 / FR-17 / NFR-S-02）。
 *
 * 認証済みユーザーの isAdmin() を判定し、管理者でなければ 403 を返す。
 * auth ミドルウェアと組み合わせて2段で保護する。
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
