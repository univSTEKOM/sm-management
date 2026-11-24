<?php

namespace App\Filament\Admin\Resources\Master\KabupatenKotas;

use App\Domain\Master\Models\KabupatenKota;
use App\Filament\Admin\Resources\Master\KabupatenKotas\Pages\ManageKabupatenKotas;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class KabupatenKotaResource extends MasterResource
{
    protected static ?string $model = KabupatenKota::class;

    protected static ?string $slug = 'master/kabupaten-kota';

    protected static ?string $modelLabel = 'Kabupaten/Kota';

    protected static ?string $pluralModelLabel = 'Kabupaten/Kota';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')->label('Kode BPS')->required()->maxLength(10)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
            ]);
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('kode')->label('Kode BPS')->searchable()->sortable(),
            TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKabupatenKotas::route('/'),
        ];
    }
}
