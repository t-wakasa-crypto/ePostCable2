# アドホック修正ログ

- [x] AD-01: SqlServerNvarcharTest.php をファイル名・クラス名ともに SqlServerVarcharTest に変更（Q-17によりvarchar検証に内容変更済みだが、ファイル名が旧名のまま残っている）
- [x] AD-02: 全モジュールのBladeビュー（Auth/Shared/ShipmentFetch/User/DeliveryNote/Dashboard/SendMailLog/Invoice/SystemSetting 配下の login/index/show 系テンプレート）に `@vite(['resources/css/app.css', 'resources/js/app.js'])` ディレクティブが記載されておらず、CSS/JSが一切読み込まれていない（localhost:8080 で確認）。全該当ファイルの `<head>` に @vite ディレクティブを追加する。
