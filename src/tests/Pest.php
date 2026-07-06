<?php

// Pest テスト共通設定。
// アプリ本体（tests/）に加え、モジュール型構成（Modules 配下）のテストにも
// TestCase と RefreshDatabase を適用し、各テストが独立した DB 状態で実行される
// ようにする。

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// アプリ本体の Feature テスト
uses(TestCase::class)->in('Feature');

// 各モジュールの Feature / Unit テスト（DB を使うため RefreshDatabase を付与）
uses(TestCase::class, RefreshDatabase::class)->in(
    __DIR__.'/../Modules/Auth/tests',
    __DIR__.'/../Modules/Dashboard/tests',
    __DIR__.'/../Modules/Invoice/tests',
    __DIR__.'/../Modules/DeliveryNote/tests',
    __DIR__.'/../Modules/SendMailLog/tests',
    __DIR__.'/../Modules/ShipmentFetch/tests',
    __DIR__.'/../Modules/SystemSetting/tests',
    __DIR__.'/../Modules/User/tests',
    __DIR__.'/../Modules/Shared/tests',
);
