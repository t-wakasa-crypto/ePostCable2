<?php

namespace Modules\ShipmentFetch\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;

/**
 * 出荷取得履歴コントローラ（詳細設計 §1.4.5 / FR-11 / NFR-M-05 / NFR-S-06）。
 *
 * 一覧は status allowlist・ページネーション。バッチ手動起動は admin のみ・非同期。
 */
class ShipmentFetchController extends Controller
{
    /** 一覧のページあたり件数（NFR-P-01） */
    private const PER_PAGE = 20;

    /**
     * 出荷取得履歴一覧。status フィルタ（allowlist）で絞り込む。
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $logs = ShipmentFetchLog::query()
            ->status(is_string($status) ? $status : null)
            ->orderByDesc('started_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('shipmentfetch::index', [
            'logs' => $logs,
            'statuses' => ShipmentFetchLog::statuses(),
            'currentStatus' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * 出荷取得バッチの手動起動（admin のみ・非同期・NFR-M-05）。
     */
    public function runBatch(): RedirectResponse
    {
        Artisan::queue('batch:fetch-shipment-data');

        return back()->with('status', '出荷取得バッチを起動しました。');
    }
}
