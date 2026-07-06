<?php

namespace Modules\ShipmentFetch\Services;

use Illuminate\Support\Facades\Http;

/**
 * 基幹システムの出荷データ取得サービス（詳細設計 §1.3.1 / §2.3 / FR-01 / NFR-P-06 / NFR-E-04）。
 *
 * BACKBONE_API_URL（config services.backbone.url）へ HTTP Client でタイムアウト30秒で
 * 接続し、レスポンスを内部の出荷データ契約（§2.3）へ整形して返す。未設定時はダミーデータ
 * （空配列）を返し、連携先なしでも起動可能にする（疎結合・NFR-E-04）。
 */
class ShipmentFetchService
{
    /** 基幹 API 接続タイムアウト秒（NFR-P-06） */
    private const TIMEOUT_SECONDS = 30;

    /**
     * 出荷データ配列を取得する。
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(): array
    {
        $url = config('services.backbone.url');

        // 未設定時はダミー（空配列）を返す（NFR-E-04）
        if (empty($url)) {
            return [];
        }

        $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);

        // レスポンス JSON を内部の出荷データ契約へ整形（§2.3 / §2.4）
        return $this->mapResponse($response->json() ?? []);
    }

    /**
     * 基幹 API レスポンス（配列）を内部の出荷データ契約（§2.3）へ整形する。
     *
     * レスポンスは出荷データ配列そのもの、または `data`/`shipments` キー配下の配列を許容する。
     *
     * @param  array<mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function mapResponse(array $raw): array
    {
        // ラッパーキー（data / shipments）配下に配列がある場合はそれを対象にする
        $records = $raw['data'] ?? $raw['shipments'] ?? $raw;

        if (! is_array($records)) {
            return [];
        }

        $shipments = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $shipments[] = $this->mapShipment($record);
        }

        return $shipments;
    }

    /**
     * 出荷データ1件を内部契約キーへ整形する（§2.3）。
     *
     * 消費税（tax / tax_amount）と内部状態（status）は Command 側で付与するため
     * ここでは扱わない（§2.4）。
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function mapShipment(array $record): array
    {
        // 送付先メールアドレス（最大3件）。配列（customer_emails）または個別キーの両形式を許容する
        $emails = $this->extractEmails($record);

        return [
            'delivery_number' => $record['delivery_number'] ?? null,
            'invoice_number' => $record['invoice_number'] ?? null,
            'customer_name' => $record['customer_name'] ?? '',
            'customer_email' => $emails[0] ?? null,
            'customer_email_2' => $emails[1] ?? null,
            'customer_email_3' => $emails[2] ?? null,
            'amount' => (int) ($record['amount'] ?? 0),
            'delivery_date' => $record['delivery_date'] ?? null,
            'issue_date' => $record['issue_date'] ?? null,
            'items' => $this->mapItems($record['items'] ?? []),
            'fixed_items' => $this->mapItems($record['fixed_items'] ?? []),
        ];
    }

    /**
     * 送付先メールアドレス（最大3件）を抽出する（§2.3・BR-04）。
     *
     * `customer_emails`（配列）が存在すればそれを、なければ個別キー
     * （customer_email / customer_email_2 / customer_email_3）を用いる。
     *
     * @param  array<string, mixed>  $record
     * @return array<int, mixed>
     */
    private function extractEmails(array $record): array
    {
        if (isset($record['customer_emails']) && is_array($record['customer_emails'])) {
            return array_values(array_slice($record['customer_emails'], 0, 3));
        }

        $emails = [
            $record['customer_email'] ?? null,
            $record['customer_email_2'] ?? null,
            $record['customer_email_3'] ?? null,
        ];

        return array_values(array_filter($emails, static fn ($e) => $e !== null));
    }

    /**
     * 明細（通常明細・固定明細）を内部契約キーへ整形する（§2.3・BR-09）。
     *
     * @param  mixed  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapItems($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped[] = [
                'item_name' => $item['item_name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (int) ($item['unit_price'] ?? 0),
                'amount' => (int) ($item['amount'] ?? 0),
                'sort_order' => $item['sort_order'] ?? null,
            ];
        }

        return $mapped;
    }
}
