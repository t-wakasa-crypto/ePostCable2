<?php

namespace Modules\DeliveryNote\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\DeliveryNote\Jobs\ProcessDeliveryNoteJob;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 納品書一覧・詳細・各操作コントローラ（詳細設計 §1.4.3 / FR-09）。
 *
 * InvoiceController と同一仕様（Invoice→DeliveryNote 読替・ProcessDeliveryNoteJob・
 * runBatch は batch:send-delivery-notes・PDF パス年月は delivery_date 基準）。
 */
class DeliveryNoteController extends Controller
{
    /** 一覧のページあたり件数（NFR-P-01） */
    private const PER_PAGE = 20;

    /** bulkRequeue の一括対象外となる retry_count 閾値（BR-07） */
    private const REQUEUE_HARD_LIMIT = 10;

    /**
     * 納品書一覧。status フィルタ（allowlist）とステータス別件数サマリーを表示する。
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $deliveryNotes = DeliveryNote::query()
            ->status(is_string($status) ? $status : null)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $summary = DeliveryNote::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('deliverynote::index', [
            'deliveryNotes' => $deliveryNotes,
            'summary' => $summary,
            'statuses' => DeliveryNote::statuses(),
            'currentStatus' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * 納品書詳細。明細と全メール送信履歴を表示する。
     */
    public function show(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['items', 'sendMailLogItems']);

        return view('deliverynote::show', ['deliveryNote' => $deliveryNote]);
    }

    /**
     * 手動再送（FR-09 / BR-07）。
     */
    public function resend(DeliveryNote $deliveryNote): RedirectResponse
    {
        DB::transaction(function () use ($deliveryNote): void {
            $deliveryNote->update(['status' => DeliveryNote::STATUS_PROCESSING]);

            $bucket = SendMailLog::manualResendBucket();

            $item = SendMailLogItem::create([
                'send_mail_log_id' => $bucket->id,
                'sendable_type' => DeliveryNote::MORPH_ALIAS,
                'sendable_id' => $deliveryNote->id,
                'status' => SendMailLogItem::STATUS_PROCESSING,
            ]);

            $bucket->increment('dispatched_count');

            ProcessDeliveryNoteJob::dispatch($deliveryNote->id, $item->id);
        });

        return back()->with('status', '再送を受け付けました。');
    }

    /**
     * メールアドレス編集（FR-09 / BR-04・admin 限定）。
     */
    public function updateEmails(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        if (! in_array($deliveryNote->status, [DeliveryNote::STATUS_FAILED, DeliveryNote::STATUS_FAILED_PERMANENT], true)) {
            return back()->withErrors(['emails' => 'この納品書はメールアドレスを編集できません。']);
        }

        $validated = $request->validate([
            'emails' => ['array', 'max:3'],
            'emails.*' => ['nullable', 'email'],
        ]);

        $emails = array_values(array_filter(
            $validated['emails'] ?? [],
            fn ($e) => is_string($e) && trim($e) !== ''
        ));

        $deliveryNote->update([
            'customer_email' => $emails[0] ?? null,
            'customer_email_2' => $emails[1] ?? null,
            'customer_email_3' => $emails[2] ?? null,
        ]);

        return back()->with('status', 'メールアドレスを更新しました。');
    }

    /**
     * 一括再キュー（FR-09 / BR-07・admin 限定）。
     */
    public function bulkRequeue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $requeued = 0;

        DB::transaction(function () use ($validated, &$requeued): void {
            $targets = DeliveryNote::whereIn('id', $validated['ids'])
                ->where('status', DeliveryNote::STATUS_FAILED)
                ->where('retry_count', '<', self::REQUEUE_HARD_LIMIT)
                ->lockForUpdate()
                ->get();

            foreach ($targets as $note) {
                $note->update([
                    'status' => DeliveryNote::STATUS_PENDING,
                    'retry_count' => $note->retry_count + 1,
                ]);
                $requeued++;
            }
        });

        return back()->with('status', $requeued.' 件を再キューしました。');
    }

    /**
     * バッチ手動起動（FR-09 / NFR-M-05・admin 限定）。
     */
    public function runBatch(): RedirectResponse
    {
        Artisan::queue('batch:send-delivery-notes');

        return back()->with('status', '納品書送信バッチを起動しました。');
    }

    /**
     * PDF ダウンロード（FR-09 / FR-15 / NFR-P-05）。年月は delivery_date 基準（Q-11）。
     */
    public function downloadPdf(DeliveryNote $deliveryNote, PdfService $pdfService): Response
    {
        $basis = $deliveryNote->delivery_date ?? $deliveryNote->created_at ?? now();
        $path = 'delivery-notes/'.$basis->format('Y').'/'.$basis->format('m').'/delivery_'.$deliveryNote->delivery_number.'.pdf';
        $filename = 'delivery_'.$deliveryNote->delivery_number.'.pdf';

        if (Storage::exists($path)) {
            return response(Storage::get($path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $pdf = $pdfService->generate('deliverynote::pdf.delivery_note', ['deliveryNote' => $deliveryNote->load('items')]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * CSV ダウンロード（FR-09 / OQ-10）。UTF-8 BOM 付き・複数送付先 ' / ' 区切り。
     */
    public function downloadCsv(Request $request): StreamedResponse
    {
        $status = $request->query('status');

        $query = DeliveryNote::query()
            ->status(is_string($status) ? $status : null)
            ->orderByDesc('created_at');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="delivery-notes.csv"',
        ];

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['納品書番号', '顧客名', '送付先', '金額', '消費税', 'ステータス', '納品日']);

            $query->chunk(200, function ($notes) use ($handle): void {
                foreach ($notes as $note) {
                    fputcsv($handle, [
                        $note->delivery_number,
                        $note->customer_name,
                        implode(' / ', $note->recipientEmails()),
                        (string) (int) $note->amount,
                        (string) (int) $note->tax_amount,
                        $note->status,
                        optional($note->delivery_date)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, 'delivery-notes.csv', $headers);
    }
}
