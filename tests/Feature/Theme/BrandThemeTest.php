<?php

/*
 * Red brand theme: one palette (BrandPalette) mirrored by CSS tokens, accessible contrast,
 * and status colours that keep their meaning instead of inheriting the brand red.
 */

use App\Domain\Jadwal\Support\KalenderEvent;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\StatusSertifikat;
use App\Domain\Shared\Enums\Tahap;
use App\Support\Theme\BrandPalette;
use Filament\Facades\Filament;

function luminance(string $hex): float
{
    $c = array_map(fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contrast(string $a, string $b): float
{
    [$hi, $lo] = [max(luminance($a), luminance($b)), min(luminance($a), luminance($b))];

    return ($hi + 0.05) / ($lo + 0.05);
}

test('the brand palette matches the specified colours', function () {
    expect(BrandPalette::PRIMARY)->toBe('#B91C1C')
        ->and(BrandPalette::DARK)->toBe('#7F1D1D')
        ->and(BrandPalette::LIGHT)->toBe('#FEE2E2')
        ->and(BrandPalette::SOFT)->toBe('#FEF2F2')
        ->and(BrandPalette::ACCENT)->toBe('#DC2626')
        ->and(BrandPalette::BACKGROUND)->toBe('#F8FAFC')
        ->and(BrandPalette::SURFACE)->toBe('#FFFFFF')
        ->and(BrandPalette::TEXT)->toBe('#1E293B')
        ->and(BrandPalette::TEXT_MUTED)->toBe('#64748B')
        ->and(BrandPalette::BORDER)->toBe('#E2E8F0');
});

test('the CSS tokens mirror the PHP palette (no drift)', function () {
    $css = file_get_contents(resource_path('css/tema.css'));
    $expected = [
        'brand-primary' => BrandPalette::PRIMARY, 'brand-dark' => BrandPalette::DARK, 'brand-light' => BrandPalette::LIGHT,
        'brand-soft' => BrandPalette::SOFT, 'brand-accent' => BrandPalette::ACCENT, 'brand-bg' => BrandPalette::BACKGROUND,
        'brand-surface' => BrandPalette::SURFACE, 'brand-text' => BrandPalette::TEXT, 'brand-text-muted' => BrandPalette::TEXT_MUTED,
        'brand-border' => BrandPalette::BORDER, 'tahap-tindak-lanjut' => BrandPalette::TAHAP_TINDAK_LANJUT,
    ];

    foreach ($expected as $token => $hex) {
        expect(preg_match('/--'.preg_quote($token).':\s*(#[0-9A-Fa-f]{6})\s*;/', $css, $m))->toBe(1, "token --{$token} missing")
            ->and(strtoupper($m[1]))->toBe(strtoupper($hex), "token --{$token} drifted");
    }
});

test('both panels use the brand primary (shade 600 is the primary red) and the slate neutral scale', function () {
    foreach (['admin', 'mentor'] as $id) {
        $colors = Filament::getPanel($id)->getColors();

        expect($colors['primary'])->toBe(BrandPalette::primary())
            ->and($colors['gray'])->toBe(BrandPalette::gray())
            ->and($colors['primary'][600])->toBe(BrandPalette::PRIMARY);
    }
});

test('both panel themes load the shared brand tokens', function () {
    foreach (['admin', 'mentor'] as $panel) {
        expect(file_get_contents(resource_path("css/filament/{$panel}/theme.css")))->toContain("@import '../../tema.css';");
    }
});

test('text and controls keep WCAG AA contrast', function (string $fg, string $bg, float $min) {
    expect(contrast($fg, $bg))->toBeGreaterThanOrEqual($min);
})->with([
    'primary button: white on primary red' => [BrandPalette::SURFACE, BrandPalette::PRIMARY, 4.5],
    'active menu: primary red on soft red' => [BrandPalette::PRIMARY, BrandPalette::SOFT, 4.5],
    'emphasis: dark red on light red' => [BrandPalette::DARK, BrandPalette::LIGHT, 4.5],
    'body text on page background' => [BrandPalette::TEXT, BrandPalette::BACKGROUND, 7.0],
    'body text on surface' => [BrandPalette::TEXT, BrandPalette::SURFACE, 7.0],
    'secondary text on page background' => [BrandPalette::TEXT_MUTED, BrandPalette::BACKGROUND, 4.5],
    'secondary text on surface' => [BrandPalette::TEXT_MUTED, BrandPalette::SURFACE, 4.5],
    'calendar: white on Awal' => ['#FFFFFF', BrandPalette::TAHAP_AWAL, 4.5],
    'calendar: white on Tindak Lanjut' => ['#FFFFFF', BrandPalette::TAHAP_TINDAK_LANJUT, 4.5],
    'calendar: white on Dibatalkan' => ['#FFFFFF', BrandPalette::DIBATALKAN, 4.5],
]);

test('calendar colours tell the stages and the cancelled state apart', function () {
    expect(KalenderEvent::WARNA_AWAL)->toBe(BrandPalette::PRIMARY)
        ->and(KalenderEvent::WARNA_TL)->toBe(BrandPalette::TAHAP_TINDAK_LANJUT)
        ->and(KalenderEvent::WARNA_BATAL)->toBe(BrandPalette::TEXT_MUTED)
        ->and([KalenderEvent::WARNA_AWAL, KalenderEvent::WARNA_TL, KalenderEvent::WARNA_BATAL])->toHaveCount(3)
        ->and(array_unique([KalenderEvent::WARNA_AWAL, KalenderEvent::WARNA_TL, KalenderEvent::WARNA_BATAL]))->toHaveCount(3);
});

test('status badges keep meaningful colours; only the stage and neutral categories avoid the danger colour', function () {
    expect(collect(StatusKegiatan::cases())->mapWithKeys(fn ($s) => [$s->value => $s->color()])->all())->toBe([
        'belum_dimulai' => 'gray', 'sedang_berlangsung' => 'warning', 'selesai' => 'success', 'dibatalkan' => 'danger',
    ]);

    // Not every status is red: danger is reserved for the cancelled state.
    expect(collect(StatusKegiatan::cases())->filter(fn ($s) => $s->color() === 'danger'))->toHaveCount(1);

    foreach ([...StatusPelatihan::cases(), ...StatusSertifikat::cases()] as $status) {
        expect($status->color())->not->toBeIn(['primary', 'danger']);
    }

    foreach (Jenjang::cases() as $jenjang) {
        expect($jenjang->color())->toBe('gray');
    }
    foreach (PosisiTahap::cases() as $posisi) {
        expect($posisi->color())->toBe('info');
    }
    expect(Tahap::Awal->color())->toBe('primary')->and(Tahap::TindakLanjut->color())->toBe('info');
});

test('no view or class hardcodes a brand hex colour outside the palette and the theme files', function () {
    $hex = '/#(?:B91C1C|7F1D1D|FEE2E2|FEF2F2|DC2626)\b/i';
    $allowed = [app_path('Support/Theme/BrandPalette.php')];

    foreach (['app', 'resources/views'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir))) as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true) || in_array($file->getPathname(), $allowed, true)) {
                continue;
            }

            expect(preg_match($hex, file_get_contents($file->getPathname())))->toBe(0, $file->getPathname().' hardcodes a brand colour');
        }
    }
});
