<?php

use Illuminate\Support\Facades\Route;

/*
 * アプリ本体のルート。
 * 各業務機能のルートは各モジュールの routes/web.php で定義する。
 * 認証ルート（/login・/logout）は Auth モジュールが提供する。
 */

// ルート（ダッシュボード）は Dashboard モジュールが提供する（routes/web.php）。
