<?php

namespace App\Filament\Admin\Resources\Sekolahs\Schemas;

use App\Domain\Shared\Enums\PosisiTahap;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SekolahInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('nama'),
                        TextEntry::make('jenjang')->badge(),
                        TextEntry::make('posisi_tahap')->label('Posisi tahap')->badge()
                            ->formatStateUsing(fn (PosisiTahap $state) => $state->label()),
                        TextEntry::make('kabupatenKota.nama')->label('Kabupaten/Kota'),
                        TextEntry::make('cabang.nama')->label('Cabang'),
                        TextEntry::make('tanggal_mitra')->label('Tanggal mitra')->date('d M Y')->placeholder('-'),
                        TextEntry::make('penugasanAktif.mentor.name')->label('Mentor penanggung jawab')->placeholder('Belum ditugaskan'),
                        TextEntry::make('narahubungUtama.nama')->label('Narahubung utama')->placeholder('-'),
                        TextEntry::make('narahubungUtama.nomor_wa')->label('WhatsApp')->placeholder('-'),
                        TextEntry::make('alamat')->columnSpanFull()->placeholder('-'),
                    ]),
            ]);
    }
}
