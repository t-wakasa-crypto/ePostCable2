<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * バッチスケジュール定義（詳細設計 §1.1.4 / FR-04 / NFR-R-03）。
 *
 * 全てのスケジュールに withoutOverlapping()（多重起動防止）と
 * runInBackground()（他ジョブをブロックしない）を付加する（NFR-R-03）。
 */

// 毎日 01:00 納品書送信バッチ（オプションなし）
Schedule::command('batch:send-delivery-notes')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// 毎日 01:30 請求書送信バッチ（オプションなし）
Schedule::command('batch:send-invoices')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

// 毎週月曜 02:00 納品書 --retry-failed 差し戻し送信
Schedule::command('batch:send-delivery-notes', ['--retry-failed'])
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();

// 毎週月曜 02:30 請求書 --retry-failed 差し戻し送信
Schedule::command('batch:send-invoices', ['--retry-failed'])
    ->weeklyOn(1, '02:30')
    ->withoutOverlapping()
    ->runInBackground();

// 出荷取得バッチ（基幹の請求データ確定〈翌日12:00〉からのバッファを見て 12:15 に起動）。
// 要件上は「12:15〜12:30」の時間幅で記述されるが、これは確定遅延を吸収するためのバッファであり、
// 起動時刻は幅の先頭 12:15 の単一時刻とする（実処理は非同期・多重起動は withoutOverlapping で防止）。
// 失敗時は出荷取得履歴画面から手動実行で救済する（FR-01 / FR-04）。
Schedule::command('batch:fetch-shipment-data')
    ->dailyAt('12:15')
    ->withoutOverlapping()
    ->runInBackground();
