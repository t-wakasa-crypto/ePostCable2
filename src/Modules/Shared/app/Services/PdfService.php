<?php

namespace Modules\Shared\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

/**
 * PDF 生成サービス（詳細設計 §1.3.2 / FR-15 / NFR-S-08 / NFR-P-05）。
 *
 * DomPDF（barryvdh/laravel-dompdf ^3.1）で Blade ビューを A4 縦 PDF バイナリへ変換する。
 * セキュリティ設定として isRemoteEnabled=false（リモートリソース無効化）と
 * chroot（storage パスへローカル参照を制限）を適用する（NFR-S-08）。
 *
 * 保存は呼び出し側の責務（バッチ時は Storage 保存・即時 DL 時は非保存・NFR-P-05）。
 */
class PdfService
{
    /**
     * Blade ビューから PDF バイナリを生成する。
     *
     * @param  string  $view  Blade ビュー名（例: 'invoice::pdf.invoice'）
     * @param  array<string, mixed>  $data  ビューへ渡すデータ
     * @return string PDF バイナリ
     *
     * @throws RuntimeException 出力が空の場合（FR-15）
     */
    public function generate(string $view, array $data = []): string
    {
        $fontDir = storage_path('fonts');

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                // 日本語フォント（IPAexGothic）を既定にする。
                // `php artisan dompdf:load-fonts`（LoadDompdfFonts）で storage/fonts に
                // 事前登録した IPAexGothic を読み込み、日本語の文字化け（豆腐表示）を防ぐ（FR-15）。
                'fontDir' => $fontDir,
                'fontCache' => $fontDir,
                'defaultFont' => 'IPAexGothic',
                'isFontSubsettingEnabled' => true,
                // リモートリソース無効化（NFR-S-08）
                'isRemoteEnabled' => false,
                // ローカルファイル参照を storage 配下に制限（chroot・NFR-S-08）
                'chroot' => storage_path(),
            ]);

        $output = $pdf->output();

        // 出力が空の場合は例外（FR-15）
        if ($output === '') {
            throw new RuntimeException('PDF の生成に失敗しました（出力が空です）。');
        }

        return $output;
    }
}
