<?php

namespace Modules\SendMailLog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\SendMailLog\Models\SendMailLog;

/**
 * メール送信履歴コントローラ（詳細設計 §1.4.4 / §5.3 / FR-10 / BR-07 / NFR-P-01）。
 *
 * 一覧は filter allowlist・20件/ページ。詳細は明細 50件/ページ。
 * 失敗ログはダッシュボード・通常一覧から除外し、filter=failed の別画面でのみ確認可能。
 * 手動完了機能（complete）は廃止（OQ-08）。
 */
class SendMailLogController extends Controller
{
    /** 一覧のページあたり件数（NFR-P-01） */
    private const PER_PAGE = 20;

    /** 詳細の明細ページあたり件数（NFR-P-01） */
    private const ITEM_PER_PAGE = 50;

    /**
     * 許可された filter 値の一覧（allowlist・NFR-S-06 / FR-10）。
     *
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return [
            'completed', 'running', 'manual_resend',
            'has_pending', 'has_sent', 'has_failure', 'has_failure_permanent', 'failed',
        ];
    }

    /**
     * 送信履歴一覧。filter allowlist で絞り込む。
     *
     * 無指定時は manual-resend と失敗ログを除外する（FR-10）。
     * filter=failed 指定時は失敗ログのみを表示する（別画面相当）。
     */
    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $filter = (is_string($filter) && in_array($filter, self::filters(), true)) ? $filter : null;

        $query = SendMailLog::query();

        if ($filter === null) {
            // 通常一覧: manual-resend と失敗ログを除外（FR-10）
            $query->excludeManualResend()->whereNull('failed_at');
        } else {
            $query->filter($filter);
        }

        $logs = $query->orderByDesc('started_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('sendmaillog::index', [
            'logs' => $logs,
            'filters' => self::filters(),
            'currentFilter' => $filter,
        ]);
    }

    /**
     * 送信履歴詳細。書類1通ごとの明細を 50件/ページで表示する。
     */
    public function show(SendMailLog $sendmaillog): View
    {
        $items = $sendmaillog->items()
            ->with('sendable')
            ->orderByDesc('id')
            ->paginate(self::ITEM_PER_PAGE);

        return view('sendmaillog::show', [
            'log' => $sendmaillog,
            'items' => $items,
        ]);
    }
}
