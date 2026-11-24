<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Shared\Enums\Tahap;

class KegiatanTlRelationManager extends KegiatanRelationManager
{
    protected static ?string $title = 'Tindak Lanjut';

    protected static ?Tahap $tahap = Tahap::TindakLanjut;
}
