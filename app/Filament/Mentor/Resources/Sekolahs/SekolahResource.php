<?php

namespace App\Filament\Mentor\Resources\Sekolahs;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Sekolahs\Schemas\SekolahInfolist;
use App\Filament\Mentor\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Mentor\Resources\Sekolahs\Pages\ViewSekolah;
use App\Support\Authorization\ScopeResolver;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only school list for mentors. Default view is "Sekolah Saya" (assigned schools);
 * when A-01 lets mentors see every school the filter can be cleared, but report notes and
 * certificate links stay limited to assigned schools (see SekolahInfolist).
 */
class SekolahResource extends Resource
{
    protected static ?string $model = Sekolah::class;

    protected static ?string $slug = 'sekolah';

    protected static ?string $modelLabel = 'Sekolah';

    protected static ?string $pluralModelLabel = 'Sekolah';

    protected static ?string $navigationLabel = 'Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->aktif && $user->role === Role::Mentor;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->tap(fn (Builder $query) => ScopeResolver::applyToSekolahQuery($query, auth()->user(), 'sekolah.view'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return SekolahInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(Sekolah::ringkasanCounts())->with(['kabupatenKota', 'penugasanAktif.mentor', 'narahubungUtama', 'sertifikat']))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')->label('Nama sekolah')->searchable()->sortable()->wrap()
                    ->description(fn (Sekolah $record) => $record->kabupatenKota->nama),
                TextColumn::make('jenjang')->badge()->color(fn (Jenjang $state) => $state->color()),
                TextColumn::make('status_pelatihan')->label('Status')->badge()
                    ->state(fn (Sekolah $record) => $record->statusPelatihan()->label())
                    ->color(fn (Sekolah $record) => $record->statusPelatihan()->color()),
                TextColumn::make('posisi_tahap')->label('Posisi tahap')->badge()
                    ->formatStateUsing(fn (PosisiTahap $state) => $state->label())
                    ->color(fn (PosisiTahap $state) => $state->color())
                    ->description(fn (Sekolah $record) => $record->progresTl()),
                TextColumn::make('penugasanAktif.mentor.name')->label('Penanggung jawab')->placeholder('Belum ditugaskan'),
                TextColumn::make('narahubungUtama.nama')->label('Narahubung')->placeholder('-')
                    ->description(fn (Sekolah $record) => $record->narahubungUtama?->nomor_wa)
                    ->url(fn (Sekolah $record) => $record->narahubungUtama?->waUrl(), shouldOpenInNewTab: true),
                TextColumn::make('sertifikat')->label('Sertifikat Awal / TL')
                    ->state(fn (Sekolah $record) => $record->statusSertifikat(Tahap::Awal)->label().' / '.$record->statusSertifikat(Tahap::TindakLanjut)->label()),
            ])
            ->filters([
                TernaryFilter::make('saya')
                    ->label('Sekolah')
                    ->trueLabel('Sekolah Saya')
                    ->falseLabel('Semua Sekolah')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => ScopeResolver::applyToSekolahQuery($q, auth()->user(), 'laporan.view_detail'),
                        false: fn (Builder $q) => $q,
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([ViewAction::make()->label('Detail')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSekolahs::route('/'),
            'view' => ViewSekolah::route('/{record}'),
        ];
    }
}
