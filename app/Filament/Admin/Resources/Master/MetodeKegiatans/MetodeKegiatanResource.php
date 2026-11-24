<?php

namespace App\Filament\Admin\Resources\Master\MetodeKegiatans;

use App\Domain\Master\Models\MetodeKegiatan;
use App\Filament\Admin\Resources\Master\MetodeKegiatans\Pages\ManageMetodeKegiatans;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class MetodeKegiatanResource extends MasterResource
{
    protected static ?string $model = MetodeKegiatan::class;

    protected static ?string $slug = 'master/metode-kegiatan';

    protected static ?string $modelLabel = 'Metode Kegiatan';

    protected static ?string $pluralModelLabel = 'Metode Kegiatan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
                Toggle::make('butuh_lokasi')->label('Wajib isi lokasi'),
                Toggle::make('butuh_tautan')->label('Wajib isi tautan pertemuan'),
            ]);
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
            IconColumn::make('butuh_lokasi')->label('Wajib lokasi')->boolean(),
            IconColumn::make('butuh_tautan')->label('Wajib tautan')->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMetodeKegiatans::route('/'),
        ];
    }
}
