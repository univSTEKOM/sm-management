<?php

namespace App\Filament\Widgets;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Summary of the schools the signed-in user may see (same scope as the table below). */
class SekolahMitraStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 0;

    protected ?string $heading = null;

    protected function getColumns(): int
    {
        return 4;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $dasar = fn () => Sekolah::query()->visibleTo($user)->whereNull('diarsipkan_at');

        return [
            Stat::make('Sekolah aktif', $dasar()->count())->description('Tidak termasuk arsip'),
            Stat::make('Mitra resmi', $dasar()->whereNotNull('tanggal_mitra')->count())->description('Pelatihan awal selesai'),
            Stat::make('Calon mitra', $dasar()->where('posisi_tahap', PosisiTahap::CalonMitra->value)->count())->description('Belum menyelesaikan pelatihan awal'),
            Stat::make('Sudah MOU', $dasar()->whereNotNull('mou_dokumen_path')->count())->description('Dokumen MOU tercatat'),
        ];
    }
}
