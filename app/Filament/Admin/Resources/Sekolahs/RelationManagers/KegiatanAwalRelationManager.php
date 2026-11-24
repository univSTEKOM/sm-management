<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Shared\Enums\Tahap;

class KegiatanAwalRelationManager extends KegiatanRelationManager
{
    protected static ?string $title = 'Pelatihan Awal';

    protected static ?Tahap $tahap = Tahap::Awal;
}
