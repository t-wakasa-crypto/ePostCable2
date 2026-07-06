<?php

/**
 * 納品書一覧・詳細の閲覧を検証するテスト（T080 / FR-09 / NFR-P-01 / NFR-S-06）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('未認証は一覧で /login へリダイレクトされる', function () {
    $this->get('/delivery-notes')->assertRedirect('/login');
});

it('認証済みで一覧が表示される', function () {
    DeliveryNote::factory()->create(['delivery_number' => 'DN-VIEW-1']);

    $this->actingAs(User::factory()->create())
        ->get('/delivery-notes')
        ->assertOk()
        ->assertSee('DN-VIEW-1');
});

it('status フィルタ（allowlist）で絞り込める', function () {
    DeliveryNote::factory()->pending()->create(['delivery_number' => 'DN-PENDING']);
    DeliveryNote::factory()->sent()->create(['delivery_number' => 'DN-SENT']);

    $this->actingAs(User::factory()->create())
        ->get('/delivery-notes?status=sent')
        ->assertOk()
        ->assertSee('DN-SENT')
        ->assertDontSee('DN-PENDING');
});

it('詳細が表示される', function () {
    $note = DeliveryNote::factory()->create(['delivery_number' => 'DN-SHOW']);
    $note->items()->create(['item_name' => '納品品目', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100]);

    $this->actingAs(User::factory()->create())
        ->get('/delivery-notes/'.$note->id)
        ->assertOk()
        ->assertSee('納品品目');
});
