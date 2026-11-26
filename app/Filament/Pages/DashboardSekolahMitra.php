<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\KlasemenKategoriChart;
use App\Filament\Widgets\SekolahMitraStats;
use App\Filament\Widgets\SekolahMitraTable;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;

/**
 * Dashboard Sekolah Mitra. Fixed order (UI brief): header, summary statistics, klasemen
 * kategori pelatihan, then the school table last because it can be very long.
 */
class DashboardSekolahMitra extends Dashboard
{
    protected static ?string $title = 'Dashboard Sekolah Mitra';

    protected static ?string $navigationLabel = 'Dasbor';

    public function getSubheading(): ?string
    {
        return 'Ringkasan, klasemen kategori pelatihan, dan daftar sekolah mitra.';
    }

    public function getWidgets(): array
    {
        return [SekolahMitraStats::class, KlasemenKategoriChart::class, SekolahMitraTable::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
