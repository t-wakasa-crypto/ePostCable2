<?php

/**
 * PdfService の PDF 生成・空出力例外を検証するテスト
 * （T041 / T042 / 詳細設計 §1.3.2 / FR-15 / NFR-S-08）。
 *
 * 帳票 Blade から PDF バイナリが生成されること、空ビューでは RuntimeException を
 * スローすることを確認する。
 */

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Shared\Services\PdfService;

uses(RefreshDatabase::class);

it('請求書 Blade から PDF バイナリを生成する', function () {
    $invoice = Invoice::factory()->create();
    $invoice->items()->create(['item_name' => '品目', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]);

    $pdf = (new PdfService)->generate('invoice::pdf.invoice', ['invoice' => $invoice->load('items')]);

    // PDF バイナリは %PDF- ヘッダで始まる
    expect($pdf)->toStartWith('%PDF-');
});

it('日本語フォント（IPAexGothic）が dompdf に事前登録される（FR-15）', function () {
    // LoadDompdfFonts コマンドで storage/fonts に IPAexGothic を登録する。
    $this->artisan('dompdf:load-fonts')->assertSuccessful();

    $installed = storage_path('fonts/installed-fonts.json');
    expect(file_exists($installed))->toBeTrue();

    // 登録済みフォント一覧に日本語フォント（ipaexgothic）が含まれること。
    $fonts = json_decode((string) file_get_contents($installed), true);
    expect($fonts)->toHaveKey('ipaexgothic');
});

it('日本語を含む請求書 PDF を IPAexGothic で生成する（文字化け防止・FR-15）', function () {
    // 事前にフォントを登録してから、日本語データを含む帳票を生成する。
    $this->artisan('dompdf:load-fonts')->assertSuccessful();

    $invoice = Invoice::factory()->create(['customer_name' => '日本語顧客名テスト株式会社']);
    $invoice->items()->create(['item_name' => '日本語品目名', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]);

    $pdf = (new PdfService)->generate('invoice::pdf.invoice', ['invoice' => $invoice->load('items')]);

    // PDF が生成され、埋め込みフォントに IPAexGothic が含まれること
    // （日本語グリフを持つフォントが実際に埋め込まれていることを確認）。
    expect($pdf)->toStartWith('%PDF-');
    expect($pdf)->toContain('IPAexGothic');
});

it('出力が空の場合は RuntimeException をスローする', function () {
    // DomPDF ファサードをモックし、output() が空文字を返す状況を再現する
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('setOptions')->andReturnSelf();
    $pdf->shouldReceive('output')->andReturn('');
    Pdf::shouldReceive('loadView')->once()->andReturn($pdf);

    expect(fn () => (new PdfService)->generate('invoice::pdf.invoice', []))
        ->toThrow(RuntimeException::class);
});
