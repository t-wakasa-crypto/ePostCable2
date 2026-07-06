<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 請求書一覧・詳細・各操作コントローラ（詳細設計 §1.4.2 / §1.4.7 / §3.4 /
 * FR-08 / FR-15 / BR-04 / BR-07 / NFR-P-01/05 / NFR-S-06）。
 *
 * 閲覧（index/show）・手動再送（resend）・メール編集（updateEmails・admin）・
 * 一括再キュー（bulkRequeue・admin）・バッチ手動起動（runBatch・admin）・
 * PDF/CSV ダウンロードを提供する。権限はルートの middleware で制御する。
 */
class InvoiceController extends Controller
{
    /** 一覧のページあたり件数（NFR-P-01） */
    private const PER_PAGE = 20;

    /** bulkRequeue の一括対象外となる retry_count 閾値（BR-07） */
    private const REQUEUE_HARD_LIMIT = 10;

    /**
     * 請求書一覧。status フィルタ（allowlist）とステータス別件数サマリーを表示する。
     */
    public function index(Request $request): View
    {
        // allowlist フィルタ（NFR-S-06）。scopeStatus が allowlist 外を無視する
        $status = $request->query('status');

        $invoices = Invoice::query()
            ->status(is_string($status) ? $status : null)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // ステータス別件数サマリー
        $summary = Invoice::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('invoice::index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'statuses' => Invoice::statuses(),
            'currentStatus' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * 請求書詳細。明細と全メール送信履歴（SendMailLogItem）を表示する。
     */
    public function show(Invoice $invoice): View
    {
        $invoice->load(['items', 'sendMailLogItems']);

        return view('invoice::show', ['invoice' => $invoice]);
    }

    /**
     * 手動再送（詳細設計 §1.4.7 / §3.4 / FR-08 / BR-07）。
     *
     * トランザクション内で processing 更新 → 当日 manual-resend 親を取得/作成 →
     * SendMailLogItem 作成 → dispatched_count++ → ProcessInvoiceJob dispatch。
     * general / admin いずれも可。
     */
    public function resend(Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($invoice): void {
            $invoice->update(['status' => Invoice::STATUS_PROCESSING]);

            $bucket = SendMailLog::manualResendBucket();

            $item = SendMailLogItem::create([
                'send_mail_log_id' => $bucket->id,
                'sendable_type' => Invoice::MORPH_ALIAS,
                'sendable_id' => $invoice->id,
                'status' => SendMailLogItem::STATUS_PROCESSING,
            ]);

            $bucket->increment('dispatched_count');

            ProcessInvoiceJob::dispatch($invoice->id, $item->id);
        });

        return back()->with('status', '再送を受け付けました。');
    }

    /**
     * メールアドレス編集（詳細設計 §1.4.2 / FR-08 / BR-04・admin 限定）。
     *
     * failed / failed_permanent のみ対象。1〜3件・各 nullable email・未入力は null 正規化。
     */
    public function updateEmails(Request $request, Invoice $invoice): RedirectResponse
    {
        // 対象 status 制限（BR-04）
        if (! in_array($invoice->status, [Invoice::STATUS_FAILED, Invoice::STATUS_FAILED_PERMANENT], true)) {
            return back()->withErrors(['emails' => 'この請求書はメールアドレスを編集できません。']);
        }

        $validated = $request->validate([
            'emails' => ['array', 'max:3'],
            'emails.*' => ['nullable', 'email'],
        ]);

        // 未入力は null 正規化し、順に customer_email / _2 / _3 へ格納する
        $emails = array_values(array_filter(
            $validated['emails'] ?? [],
            fn ($e) => is_string($e) && trim($e) !== ''
        ));

        $invoice->update([
            'customer_email' => $emails[0] ?? null,
            'customer_email_2' => $emails[1] ?? null,
            'customer_email_3' => $emails[2] ?? null,
        ]);

        return back()->with('status', 'メールアドレスを更新しました。');
    }

    /**
     * 一括再キュー（詳細設計 §1.4.2 / FR-08 / BR-07・admin 限定）。
     *
     * failed → pending へ一括更新・retry_count++。retry_count>=10 は対象外（failed のまま）。
     * retry_count>=3 を含む場合の確認ダイアログはビュー側で表示する（一括自体は制限しない）。
     */
    public function bulkRequeue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $requeued = 0;

        DB::transaction(function () use ($validated, &$requeued): void {
            $targets = Invoice::whereIn('id', $validated['ids'])
                ->where('status', Invoice::STATUS_FAILED)
                ->where('retry_count', '<', self::REQUEUE_HARD_LIMIT)
                ->lockForUpdate()
                ->get();

            foreach ($targets as $invoice) {
                $invoice->update([
                    'status' => Invoice::STATUS_PENDING,
                    'retry_count' => $invoice->retry_count + 1,
                ]);
                $requeued++;
            }
        });

        return back()->with('status', $requeued.' 件を再キューしました。');
    }

    /**
     * バッチ手動起動（詳細設計 §1.4.2 / FR-08 / NFR-M-05・admin 限定）。
     *
     * Artisan::queue による非同期起動。画面は即時受付を返す。
     */
    public function runBatch(): RedirectResponse
    {
        Artisan::queue('batch:send-invoices');

        return back()->with('status', '請求書送信バッチを起動しました。');
    }

    /**
     * PDF ダウンロード（詳細設計 §1.4.2 / FR-08 / FR-15 / NFR-P-05）。
     *
     * Storage にあれば返却、なければ PdfService で即時生成（保存しない）。
     */
    public function downloadPdf(Invoice $invoice, PdfService $pdfService): Response
    {
        $basis = $invoice->issue_date ?? $invoice->created_at ?? now();
        $path = 'invoices/'.$basis->format('Y').'/'.$basis->format('m').'/invoice_'.$invoice->invoice_number.'.pdf';
        $filename = 'invoice_'.$invoice->invoice_number.'.pdf';

        if (Storage::exists($path)) {
            return response(Storage::get($path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        // 未保存時は即時生成（Storage 保存なし・NFR-P-05）
        $pdf = $pdfService->generate('invoice::pdf.invoice', ['invoice' => $invoice->load('items')]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * CSV ダウンロード（詳細設計 §1.4.2 / FR-08 / OQ-10）。
     *
     * status allowlist、UTF-8 BOM 付き、複数送付先は ' / ' 区切り。
     */
    public function downloadCsv(Request $request): StreamedResponse
    {
        $status = $request->query('status');

        $query = Invoice::query()
            ->status(is_string($status) ? $status : null)
            ->orderByDesc('created_at');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoices.csv"',
        ];

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM（Excel での文字化け防止・OQ-10）
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['請求書番号', '顧客名', '送付先', '金額', '消費税', 'ステータス', '発行日']);

            $query->chunk(200, function ($invoices) use ($handle): void {
                foreach ($invoices as $invoice) {
                    fputcsv($handle, [
                        $invoice->invoice_number,
                        $invoice->customer_name,
                        implode(' / ', $invoice->recipientEmails()),
                        (string) (int) $invoice->amount,
                        (string) (int) $invoice->tax_amount,
                        $invoice->status,
                        optional($invoice->issue_date)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, 'invoices.csv', $headers);
    }
}
