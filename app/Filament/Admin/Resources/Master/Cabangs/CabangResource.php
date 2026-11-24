<?php

namespace App\Filament\Admin\Resources\Master\Cabangs;

use App\Domain\Master\Models\Cabang;
use App\Filament\Admin\Resources\Master\Cabangs\Pages\ManageCabangs;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class CabangResource extends MasterResource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $slug = 'master/cabang';

    protected static ?string $modelLabel = 'Cabang';

    protected static ?string $pluralModelLabel = 'Cabang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')->label('Kode')->required()->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                Textarea::make('alamat')->label('Alamat')->maxLength(2000)->columnSpanFull(),
            ]);
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
            TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
            TextColumn::make('alamat')->label('Alamat')->limit(60)->toggleable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCabangs::route('/'),
        ];
    }
}
