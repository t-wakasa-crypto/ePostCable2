<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\User\Models\User;

/**
 * ユーザー管理コントローラ（詳細設計 §1.4.6 / FR-12 / BR-08）。admin 限定。
 *
 * 一覧（role フィルタ・退職者既定除外）・作成・編集（password 入力時のみ更新・
 * retired トグル）・削除（物理・自己削除不可）を提供する。
 */
class UserController extends Controller
{
    /** 一覧のページあたり件数（NFR-P-01） */
    private const PER_PAGE = 20;

    /**
     * ユーザー一覧。role フィルタ（allowlist）・退職者は既定除外（include_retired で表示）。
     */
    public function index(Request $request): View
    {
        $role = $request->query('role');
        $includeRetired = $request->boolean('include_retired');

        $query = User::query()->role(is_string($role) ? $role : null);

        // 退職者は既定で除外（FR-12 / BR-08）
        if (! $includeRetired) {
            $query->active();
        }

        $users = $query->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('user::index', [
            'users' => $users,
            'roles' => User::roles(),
            'currentRole' => is_string($role) ? $role : null,
            'includeRetired' => $includeRetired,
        ]);
    }

    /**
     * ユーザー作成。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('status', 'ユーザーを作成しました。');
    }

    /**
     * ユーザー更新。password は入力時のみ更新。retired トグルで retired_at set/解除。
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
            'retired' => ['sometimes', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        // password は入力時のみ更新（BR-08）
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        // retired トグルで retired_at set/解除（BR-08）
        $retired = $request->boolean('retired');
        $user->retired_at = $retired ? ($user->retired_at ?? now()) : null;

        $user->save();

        return redirect()->route('users.index')->with('status', 'ユーザーを更新しました。');
    }

    /**
     * ユーザー削除（物理削除・自己削除不可・BR-08）。
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => '自分自身は削除できません。']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'ユーザーを削除しました。');
    }
}
