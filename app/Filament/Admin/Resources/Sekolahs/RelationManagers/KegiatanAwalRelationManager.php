<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Support\JadwalActions;

class KegiatanAwalRelationManager extends KegiatanRelationManager
{
    protected static ?string $title = 'Pelatihan Awal';

    protected static ?Tahap $tahap = Tahap::Awal;

    protected function tableHeaderActions(): array
    {
        return [JadwalActions::tambahAwal(function (): Sekolah {
            /** @var Sekolah $sekolah */
            $sekolah = $this->getOwnerRecord();

            return $sekolah;
        })];
    }
}
