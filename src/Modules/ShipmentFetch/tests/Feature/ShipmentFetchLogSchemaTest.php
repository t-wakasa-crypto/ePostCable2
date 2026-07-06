<?php

/**
 * shipment_fetch_logs / system_settings テーブルのスキーマが db-design §2.8 / §2.9 と
 * 一致することを検証するテスト（T017）。
 */

use Illuminate\Support\Facades\Schema;

it('shipment_fetch_logs が必要なカラムを持つ', function () {
    expect(Schema::hasColumns('shipment_fetch_logs', [
        'id', 'status', 'started_at', 'completed_at', 'fetched_count',
        'created_delivery_note_count', 'created_invoice_count', 'skipped_count',
        'execution_seconds', 'error_message', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('system_settings が必要なカラムを持つ', function () {
    expect(Schema::hasColumns('system_settings', [
        'id', 'key', 'value', 'type', 'min_value', 'max_value', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
