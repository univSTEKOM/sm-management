<?php

namespace App\Filament\Admin\Resources\Sekolahs;

use App\Domain\Sekolah\Models\Sekolah;
use App\Filament\Admin\Resources\Sekolahs\Pages\CreateSekolah;
use App\Filament\Admin\Resources\Sekolahs\Pages\EditSekolah;
use App\Filament\Admin\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Admin\Resources\Sekolahs\Pages\ViewSekolah;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanAwalRelationManager;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanTlRelationManager;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\NarahubungRelationManager;
use App\Filament\Admin\Resources\Sekolahs\Schemas\SekolahForm;
use App\Filament\Admin\Resources\Sekolahs\Schemas\SekolahInfolist;
use App\Filament\Admin\Resources\Sekolahs\Tables\SekolahsTable;
use App\Support\Authorization\ScopeResolver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SekolahResource extends Resource
{
    protected static ?string $model = Sekolah::class;

    protected static ?string $slug = 'sekolah';

    protected static ?string $modelLabel = 'Sekolah';

    protected static ?string $navigationLabel = 'Sekolah Mitra';

    protected static string|\UnitEnum|null $navigationGroup = 'Sekolah Mitra';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return SekolahForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SekolahInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SekolahsTable::configure($table);
    }

    /** Every read goes through the branch/assignment scope (Implementation Plan 4.5). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->tap(fn (Builder $query) => ScopeResolver::applyToSekolahQuery($query, auth()->user(), 'sekolah.view'));
    }

    public static function getRelations(): array
    {
        return [
            NarahubungRelationManager::class,
            KegiatanAwalRelationManager::class,
            KegiatanTlRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSekolahs::route('/'),
            'create' => CreateSekolah::route('/create'),
            'view' => ViewSekolah::route('/{record}'),
            'edit' => EditSekolah::route('/{record}/edit'),
        ];
    }
}
