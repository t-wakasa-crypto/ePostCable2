<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * リトライ不可能（恒久的）なジョブ失敗を表す例外。
 *
 * 送付先メールアドレスが0件、または無効なアドレスが含まれる場合など、
 * 自動リトライを繰り返しても解決しない失敗をこの例外で表現する。
 * ジョブの failed() ハンドラは本例外を検知した場合、書類ステータスを
 * `failed_permanent` に更新し、自動リトライ対象から除外する。
 *
 * 対応要件: BR-01 / NFR-R-06 / 詳細設計 §1.2.4
 */
class PermanentJobFailureException extends RuntimeException {}
