<?php

/**
 * 共通例外 PermanentJobFailureException の基本挙動を検証するテスト。
 *
 * この例外はジョブの「恒久的失敗（リトライ不可）」を表し、RuntimeException を
 * 継承している必要がある（失敗ハンドラで instanceof 判定に使うため）。
 * 対応要件: BR-01 / NFR-R-06
 */

use App\Exceptions\PermanentJobFailureException;

it('RuntimeException を継承している', function () {
    $exception = new PermanentJobFailureException('送付先が0件です');

    expect($exception)->toBeInstanceOf(RuntimeException::class);
});

it('メッセージを保持する', function () {
    $exception = new PermanentJobFailureException('無効なアドレスです');

    expect($exception->getMessage())->toBe('無効なアドレスです');
});
