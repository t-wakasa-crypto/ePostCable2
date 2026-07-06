<?php

/**
 * 日本語フォント（IPAexGothic）を dompdf のフォントキャッシュに登録するコマンド
 * （FR-15 / 詳細設計 §1.3.2）。
 *
 * ■ なぜ必要か？
 *   dompdf に同梱されている DejaVu Sans 系フォントは日本語グリフを持たないため、
 *   そのままでは PDF 内の日本語が文字化け（豆腐＝□ 表示）する。
 *   このコマンドで IPAexGothic を storage/fonts へ事前登録（LoadDompdfFonts）することで、
 *   PdfService が生成する請求書・納品書 PDF に日本語を正しく描画できる。
 *
 * ■ 実行方法
 *   php artisan dompdf:load-fonts
 *   （Docker イメージのビルド時やセットアップ時に一度実行しておく）
 */

namespace Modules\Shared\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;

class LoadDompdfFonts extends Command
{
    protected $signature = 'dompdf:load-fonts';

    protected $description = 'Register Japanese fonts (IPAexGothic) into dompdf font cache';

    /**
     * フォントファイルを探して storage/fonts へコピーし、dompdf に登録する。
     */
    public function handle(): int
    {
        // ▼ フォント格納先（storage/fonts）を用意する（無ければ作成）
        $fontDir = storage_path('fonts');

        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }

        // ▼ dompdf のオプション（フォントディレクトリ・キャッシュ・chroot）
        $options = new Options;
        $options->setFontDir($fontDir);
        $options->setFontCache($fontDir);
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();

        // ▼ IPAexGothic の TTF 候補パス（環境ごとにインストール先が異なるため複数候補を探索）
        $candidates = [
            '/usr/share/fonts/opentype/ipaexfont-gothic/ipaexg.ttf',
            '/usr/share/fonts/truetype/fonts-ipa/ipaexg.ttf',
            '/usr/share/fonts/ipaexfont/ipaexg.ttf',
            '/usr/share/fonts/ipa-gothic/ipaexg.ttf',
            '/usr/share/fonts/ipa-font/ipaexg.ttf',
            '/usr/share/fonts/ipaexg.ttf',
        ];

        $ttfPath = null;
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $ttfPath = $path;
                break;
            }
        }

        if ($ttfPath === null) {
            $this->error('IPAexGothic font file not found. Please install the font-ipa package.');
            $this->line('Tried paths: '.implode(', ', $candidates));

            return self::FAILURE;
        }

        $this->line("Font found: {$ttfPath}");

        // ▼ dompdf の chroot 制限を回避するためフォントを storage/fonts へコピーする
        $localTtf = $fontDir.'/ipaexg.ttf';
        if (! file_exists($localTtf)) {
            copy($ttfPath, $localTtf);
        }

        // ▼ 太字・斜体など4パターンの字形を登録する（同じ TTF を流用）
        $styles = [
            ['family' => 'IPAexGothic', 'weight' => 'normal', 'style' => 'normal'],
            ['family' => 'IPAexGothic', 'weight' => 'bold',   'style' => 'normal'],
            ['family' => 'IPAexGothic', 'weight' => 'normal', 'style' => 'italic'],
            ['family' => 'IPAexGothic', 'weight' => 'bold',   'style' => 'italic'],
        ];

        foreach ($styles as $style) {
            $result = $fontMetrics->registerFont($style, $localTtf);
            if (! $result) {
                $this->error("Failed to register IPAexGothic ({$style['weight']}/{$style['style']}).");

                return self::FAILURE;
            }
        }

        // ▼ 登録内容をフォントキャッシュ（installed-fonts.json）に保存する
        $fontMetrics->saveFontFamilies();
        $this->info('IPAexGothic font registered successfully (normal/bold/italic/bold-italic).');

        return self::SUCCESS;
    }
}
