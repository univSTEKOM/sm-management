<?php

namespace App\Filament\Admin\Resources\Master\JenisKegiatans;

use App\Domain\Master\Models\JenisKegiatan;
use App\Filament\Admin\Resources\Master\JenisKegiatans\Pages\ManageJenisKegiatans;
use App\Filament\Support\MasterResource;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class JenisKegiatanResource extends MasterResource
{
    protected static ?string $model = JenisKegiatan::class;

    protected static ?string $slug = 'master/jenis-kegiatan';

    protected static ?string $modelLabel = 'Jenis Kegiatan';

    protected static ?string $pluralModelLabel = 'Jenis Kegiatan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
            ]);
    }

    protected static function columns(): array
    {
        return [
            TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJenisKegiatans::route('/'),
        ];
    }
}
