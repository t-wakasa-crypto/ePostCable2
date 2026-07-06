<?php

/**
 * キャッシュ/ロックの database ⇄ redis 切替確認テスト（T112 / NFR-E-03 / NFR-R-01）。
 *
 * Cache::lock による二重起動防止が、database ストア・redis ストアの双方で
 * 同一に動作する（保持中は再取得できず、解放後は再取得できる）ことを確認する。
 * これにより CACHE_STORE をどちらに切り替えても排他制御が成立することを担保する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * 指定ストアで「保持中は再取得不可・解放後は再取得可」を検証する共通アサーション。
 */
function assertLockExclusion(string $store): void
{
    $key = 'batch:test-lock-'.$store.'-'.uniqid();

    $first = Cache::store($store)->lock($key, 3600);
    expect($first->get())->toBeTrue();

    // 保持中は別ロックが取得できない（多重起動防止・NFR-R-01）
    $second = Cache::store($store)->lock($key, 3600);
    expect($second->get())->toBeFalse();

    // 解放後は再取得できる
    $first->release();
    $third = Cache::store($store)->lock($key, 3600);
    expect($third->get())->toBeTrue();
    $third->release();
}

it('database ストアで Cache::lock の排他制御が成立する', function () {
    assertLockExclusion('database');
});

it('redis ストアで Cache::lock の排他制御が成立する', function () {
    // redis 未接続環境ではスキップ（開発環境は redis コンテナ稼働・BR-10）
    try {
        Cache::store('redis')->getStore()->connection()->ping();
    } catch (\Throwable $e) {
        $this->markTestSkipped('redis へ接続できないためスキップします: '.$e->getMessage());
    }

    assertLockExclusion('redis');
})->group('redis');
