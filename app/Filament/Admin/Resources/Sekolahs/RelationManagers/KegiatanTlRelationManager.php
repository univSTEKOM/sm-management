<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Support\JadwalActions;
use Filament\Tables\Table;

class KegiatanTlRelationManager extends KegiatanRelationManager
{
    protected static ?string $title = 'Tindak Lanjut';

    protected static ?Tahap $tahap = Tahap::TindakLanjut;

    public function table(Table $table): Table
    {
        // BR-TL-03: the 4 online + 1 offline pattern is a reference only.
        return parent::table($table)->description(fn () => $this->sekolah()->komposisiTl());
    }

    protected function tableHeaderActions(): array
    {
        return [JadwalActions::tambahSesi(fn () => $this->sekolah())];
    }

    private function sekolah(): Sekolah
    {
        /** @var Sekolah $sekolah */
        $sekolah = $this->getOwnerRecord();

        return $sekolah;
    }
}
