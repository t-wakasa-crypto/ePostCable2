<?php

/**
 * バッチスケジュール定義を検証するテスト（T090 / 詳細設計 §1.1.4 / FR-04 / NFR-R-03）。
 *
 * 5つのスケジュール登録（送信2 / retry-failed 2 / 出荷取得1）が存在し、
 * withoutOverlapping + runInBackground が付与されていることを確認する。
 */

use Illuminate\Console\Scheduling\Schedule;

it('全バッチスケジュールが登録されている', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())->map(fn ($e) => $e->command)->all();

    $joined = implode("\n", $commands);

    // 送信バッチ（オプションなし）
    expect($joined)->toContain('batch:send-delivery-notes');
    expect($joined)->toContain('batch:send-invoices');
    // --retry-failed 差し戻し
    expect($joined)->toContain('batch:send-delivery-notes --retry-failed');
    expect($joined)->toContain('batch:send-invoices --retry-failed');
    // 出荷取得
    expect($joined)->toContain('batch:fetch-shipment-data');

    // 5件登録されている
    expect($schedule->events())->toHaveCount(5);
});

it('全スケジュールに多重起動防止・バックグラウンド実行が付与されている', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    foreach ($schedule->events() as $event) {
        // withoutOverlapping()
        expect($event->withoutOverlapping)->toBeTrue();
        // runInBackground()
        expect($event->runInBackground)->toBeTrue();
    }
});
