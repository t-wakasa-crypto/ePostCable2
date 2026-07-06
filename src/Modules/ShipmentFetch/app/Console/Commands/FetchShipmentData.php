<?php

namespace Modules\ShipmentFetch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\Invoice\Models\Invoice;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;
use Modules\ShipmentFetch\Services\ShipmentFetchService;
use Throwable;

/**
 * 出荷データ取得バッチ（詳細設計 §1.1.1 / §3.1 / FR-01 / BR-02 / BR-05 / NFR-R-01）。
 *
 * 基幹システムから出荷データを取得し、納品書・請求書を pending で作成する。
 * Cache::lock で多重起動を防止し、ShipmentFetchLog に実行結果を記録する。
 */
class FetchShipmentData extends Command
{
    /** ロックキー（NFR-R-01） */
    private const LOCK_KEY = 'batch:fetch-shipment-data';

    /** ロック TTL 秒（NFR-R-01） */
    private const LOCK_TTL = 3600;

    /** 税率（%）。出荷取得時 10 固定（BR-02） */
    private const TAX_RATE = 10;

    protected $signature = 'batch:fetch-shipment-data';

    protected $description = '基幹システムから出荷データを取得し、納品書・請求書を作成する';

    public function handle(ShipmentFetchService $service): int
    {
        // 多重起動防止（NFR-R-01）
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        if (! $lock->get()) {
            Log::info('FetchShipmentData: ロック取得失敗のためスキップします');

            return self::SUCCESS;
        }

        $startedAt = now();
        $log = ShipmentFetchLog::create([
            'status' => ShipmentFetchLog::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        $fetched = 0;
        $createdDeliveryNotes = 0;
        $createdInvoices = 0;
        $skipped = 0;

        try {
            $shipments = $service->fetch();
            $fetched = count($shipments);

            foreach ($shipments as $shipment) {
                // 空白のみの customer_email はバリデーションエラーとしてスキップ（Q-09 / BR-04）
                if (! $this->hasValidPrimaryEmail($shipment)) {
                    Log::warning('FetchShipmentData: customer_email が空白のためスキップ', [
                        'delivery_number' => $shipment['delivery_number'] ?? null,
                        'invoice_number' => $shipment['invoice_number'] ?? null,
                    ]);

                    continue;
                }

                DB::transaction(function () use ($shipment, &$createdDeliveryNotes, &$createdInvoices, &$skipped) {
                    // 固定明細（送料・値引き等）を通常明細形式へ変換し、金額整合性を検証する（T051 / DB-Q-02）
                    [$items, $isConsistent] = $this->normalizeItems($shipment);
                    $amount = (int) ($shipment['amount'] ?? 0);
                    $taxAmount = (int) round($amount * self::TAX_RATE / 100);

                    // 不一致は書類をエラーマーキング（failed_permanent で自動送信・一括再送対象から除外し手動対応に委ねる）
                    $status = $isConsistent ? Invoice::STATUS_PENDING : Invoice::STATUS_FAILED_PERMANENT;

                    if (! $isConsistent) {
                        Log::warning('FetchShipmentData: 明細合計と amount が不一致のためエラーマーキング', [
                            'delivery_number' => $shipment['delivery_number'] ?? null,
                            'invoice_number' => $shipment['invoice_number'] ?? null,
                        ]);
                    }

                    // 納品書（重複スキップ・BR-05）
                    $deliveryNumber = $shipment['delivery_number'] ?? null;
                    if ($deliveryNumber !== null) {
                        if (DeliveryNote::where('delivery_number', $deliveryNumber)->exists()) {
                            $skipped++;
                        } else {
                            $note = DeliveryNote::create([
                                'delivery_number' => $deliveryNumber,
                                'customer_name' => $shipment['customer_name'] ?? '',
                                'customer_email' => $shipment['customer_email'],
                                'customer_email_2' => $shipment['customer_email_2'] ?? null,
                                'customer_email_3' => $shipment['customer_email_3'] ?? null,
                                'amount' => $amount,
                                'tax' => self::TAX_RATE,
                                'tax_amount' => $taxAmount,
                                'status' => $status,
                                'delivery_date' => $shipment['delivery_date'] ?? null,
                                'issue_date' => $shipment['issue_date'] ?? null,
                            ]);
                            $note->items()->createMany($items);
                            $createdDeliveryNotes++;
                        }
                    }

                    // 請求書（重複スキップ・BR-05）
                    $invoiceNumber = $shipment['invoice_number'] ?? null;
                    if ($invoiceNumber !== null) {
                        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                            $skipped++;
                        } else {
                            $invoice = Invoice::create([
                                'invoice_number' => $invoiceNumber,
                                'customer_name' => $shipment['customer_name'] ?? '',
                                'customer_email' => $shipment['customer_email'],
                                'customer_email_2' => $shipment['customer_email_2'] ?? null,
                                'customer_email_3' => $shipment['customer_email_3'] ?? null,
                                'amount' => $amount,
                                'tax' => self::TAX_RATE,
                                'tax_amount' => $taxAmount,
                                'status' => $status,
                                'issue_date' => $shipment['issue_date'] ?? null,
                            ]);
                            $invoice->items()->createMany($items);
                            $createdInvoices++;
                        }
                    }
                });
            }

            $log->update([
                'status' => ShipmentFetchLog::STATUS_COMPLETED,
                'fetched_count' => $fetched,
                'created_delivery_note_count' => $createdDeliveryNotes,
                'created_invoice_count' => $createdInvoices,
                'skipped_count' => $skipped,
                'execution_seconds' => now()->diffInMilliseconds($startedAt) / 1000,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            // 例外時はログを failed 更新（FR-01 / §6）
            $log->update([
                'status' => ShipmentFetchLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'fetched_count' => $fetched,
                'created_delivery_note_count' => $createdDeliveryNotes,
                'created_invoice_count' => $createdInvoices,
                'skipped_count' => $skipped,
                'execution_seconds' => now()->diffInMilliseconds($startedAt) / 1000,
            ]);
            Log::error('FetchShipmentData: 例外発生', ['message' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * 主送付先（customer_email）が空白のみでないか判定する（Q-09 / BR-04）。
     *
     * @param  array<string, mixed>  $shipment
     */
    private function hasValidPrimaryEmail(array $shipment): bool
    {
        $email = $shipment['customer_email'] ?? null;

        return is_string($email) && trim($email) !== '';
    }

    /**
     * 固定明細（送料・値引き等）を通常明細形式へ変換し、明細合計と amount の整合性を検証する
     * （T051 / FR-01 / DB-Q-02）。
     *
     * @param  array<string, mixed>  $shipment
     * @return array{0: array<int, array<string, mixed>>, 1: bool} 明細配列と整合性フラグ
     */
    private function normalizeItems(array $shipment): array
    {
        $items = [];

        foreach ($shipment['items'] ?? [] as $item) {
            $items[] = [
                'item_name' => $item['item_name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (int) ($item['unit_price'] ?? 0),
                'amount' => (int) ($item['amount'] ?? 0),
                'sort_order' => $item['sort_order'] ?? null,
            ];
        }

        // 固定明細を通常明細（quantity=1・unit_price=amount）へ変換
        foreach ($shipment['fixed_items'] ?? [] as $fixed) {
            $fixedAmount = (int) ($fixed['amount'] ?? 0);
            $items[] = [
                'item_name' => $fixed['item_name'] ?? '固定明細',
                'quantity' => 1,
                'unit_price' => $fixedAmount,
                'amount' => $fixedAmount,
                'sort_order' => $fixed['sort_order'] ?? null,
            ];
        }

        // 明細合計と amount の整合性検証（不一致は呼び出し側でエラーマーキング）。
        // 基幹 API が明細内訳を返さず amount のみのケース（items も fixed_items も空）は、
        // 内訳が存在しないため整合性検証の対象外とし consistent 扱いにする
        // （itemsTotal=0 ≠ amount による false-positive で全件が誤って failed_permanent に
        //  マーキングされる事象を防止する。DB-Q-02 / FR-01）。
        if ($items === []) {
            return [$items, true];
        }

        $itemsTotal = array_sum(array_column($items, 'amount'));
        $isConsistent = $itemsTotal === (int) ($shipment['amount'] ?? 0);

        return [$items, $isConsistent];
    }
}
