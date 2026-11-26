<?php

namespace App\Filament\Widgets;

use App\Domain\Sekolah\Support\KlasemenKategori;
use App\Models\User;
use App\Support\Theme\BrandPalette;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Horizontal bar chart of the klasemen (FR-RNK-03, FR-DSH-04): the training categories schools
 * choose most, ranked. The numbers come from {@see KlasemenKategori}; this widget only draws them.
 * Rank 1 (ties included) is the dark red, the rest the brand red.
 */
class KlasemenKategoriChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Klasemen Kategori Pelatihan';

    protected ?string $description = 'Jumlah sekolah mitra per kategori, seluruh Jawa Tengah. Satu sekolah dapat terhitung di lebih dari satu kategori.';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && KlasemenKategori::bolehMelihat($user);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return Collection<int, array{id: int, nama: string, jumlah: int, peringkat: int}> */
    private function baris(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return KlasemenKategori::handle($user);
    }

    protected function getData(): array
    {
        $baris = $this->baris();

        return [
            'datasets' => [[
                'label' => 'Jumlah sekolah',
                'data' => $baris->pluck('jumlah')->all(),
                'backgroundColor' => $baris->map(fn (array $b) => $b['peringkat'] === 1 && $b['jumlah'] > 0 ? BrandPalette::DARK : BrandPalette::PRIMARY)->all(),
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ]],
            // Multi-line labels (array) so long category names wrap instead of being cut off.
            'labels' => $baris->map(fn (array $b) => [...self::bungkus($b['peringkat'].'. '.$b['nama']), $b['jumlah'].' sekolah'])->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => BrandPalette::BORDER]],
                'y' => ['grid' => ['display' => false], 'ticks' => ['autoSkip' => false, 'font' => ['size' => 12]]],
            ],
            'layout' => ['padding' => ['right' => 8]],
        ];
    }

    protected function getMaxHeight(): ?string
    {
        return max(220, $this->baris()->count() * 64 + 60).'px';
    }

    /** @return array<int, string> */
    private static function bungkus(string $teks, int $lebar = 20): array
    {
        return explode("\n", wordwrap($teks, $lebar, "\n", true));
    }
}
