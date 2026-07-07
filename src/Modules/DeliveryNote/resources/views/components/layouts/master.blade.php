{{--
    モジュール共通レイアウト（FA-01）
    実体は Shared モジュールの共通レイアウト（layouts.app）へ委譲し、
    サイドナビ・ヘッダーの一元管理を実現する（NFR-M / detailed-design §1A.1）。
    ページ側からは title 属性で画面タイトルを渡す。
--}}
@props(['title' => null])

<x-shared::layouts.app :title="$title">
    {{ $slot }}
</x-shared::layouts.app>
