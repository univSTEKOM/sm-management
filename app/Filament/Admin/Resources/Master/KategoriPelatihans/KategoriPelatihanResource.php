<?php

namespace App\Filament\Admin\Resources\Master\KategoriPelatihans;

use App\Domain\Master\Models\KategoriPelatihan;
use App\Filament\Admin\Resources\Master\KategoriPelatihans\Pages\ManageKategoriPelatihans;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class KategoriPelatihanResource extends MasterResource
{
    protected static ?string $model = KategoriPelatihan::class;

    protected static ?string $slug = 'master/kategori-pelatihan';

    protected static ?string $modelLabel = 'Kategori Pelatihan';

    protected static ?string $pluralModelLabel = 'Kategori Pelatihan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
                Textarea::make('deskripsi')->label('Deskripsi')->maxLength(2000)->columnSpanFull(),
            ]);
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
            TextColumn::make('deskripsi')->label('Deskripsi')->limit(60)->toggleable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKategoriPelatihans::route('/'),
        ];
    }
}
