<?php

/**
 * 出荷取得履歴の一覧・バッチ手動起動を検証するテスト（T102 / FR-11 / NFR-M-05 / NFR-S-06）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('未認証は一覧で /login へリダイレクトされる', function () {
    $this->get('/shipment-fetch-logs')->assertRedirect('/login');
});

it('認証済みで一覧が表示され status allowlist で絞り込める', function () {
    ShipmentFetchLog::factory()->completed()->create(['fetched_count' => 111]);
    ShipmentFetchLog::factory()->failed()->create(['fetched_count' => 222]);

    $this->actingAs(User::factory()->create())
        ->get('/shipment-fetch-logs?status=completed')
        ->assertOk()
        ->assertSee('111')
        ->assertDontSee('222');
});

it('allowlist 外の status は無視され全件表示される', function () {
    ShipmentFetchLog::factory()->completed()->create(['fetched_count' => 333]);

    $this->actingAs(User::factory()->create())
        ->get('/shipment-fetch-logs?status=__invalid__')
        ->assertOk()
        ->assertSee('333');
});

it('バッチ手動起動ボタンは admin のみ表示される', function () {
    $this->actingAs(User::factory()->create())
        ->get('/shipment-fetch-logs')
        ->assertOk()
        ->assertDontSee('出荷取得バッチを起動');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/shipment-fetch-logs')
        ->assertOk()
        ->assertSee('出荷取得バッチを起動');
});

it('非 admin はバッチ手動起動で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->post('/shipment-fetch-logs/run-batch')
        ->assertForbidden();
});

it('admin はバッチを手動起動できる', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/shipment-fetch-logs/run-batch')
        ->assertRedirect();
});
