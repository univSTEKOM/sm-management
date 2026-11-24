<?php

namespace App\Filament\Support;

use App\Domain\Master\Actions\DeleteMasterData;
use App\Domain\Master\Actions\SaveMasterData;
use App\Domain\Master\Actions\SetMasterDataAktif;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Shared behaviour of the Superadmin master data resources (FR-MST-01/02). Every write
 * goes through the Master domain actions; deletion is offered only for unused records.
 */
abstract class MasterResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static bool $isGloballySearchable = false;

    /**
     * Columns specific to the master type; the status column and actions are appended.
     *
     * @return array<int, mixed>
     */
    abstract protected static function columns(): array;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...static::columns(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Status')->trueLabel('Aktif')->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Model $record, array $data, EditAction $action) => RunsDomainAction::attempt(
                        $action,
                        fn () => app(SaveMasterData::class)->handle(auth()->user(), static::getModel(), $data, $record),
                    )),
                self::statusAction(false),
                self::statusAction(true),
                Action::make('hapus')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Hanya data yang belum pernah dipakai yang dapat dihapus.')
                    ->visible(fn (Model $record) => Gate::allows('delete', $record))
                    ->action(fn (Model $record, Action $action) => RunsDomainAction::run(
                        $action,
                        fn () => app(DeleteMasterData::class)->handle(auth()->user(), $record),
                        'Data dihapus',
                    )),
            ])
            ->toolbarActions([])
            ->defaultSort('nama');
    }

    private static function statusAction(bool $aktif): Action
    {
        return Action::make($aktif ? 'aktifkan' : 'nonaktifkan')
            ->label($aktif ? 'Aktifkan' : 'Nonaktifkan')
            ->icon($aktif ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedNoSymbol)
            ->color($aktif ? 'success' : 'warning')
            ->requiresConfirmation()
            ->modalDescription($aktif ? null : 'Data nonaktif tetap tampil pada data lama, tetapi tidak dapat dipilih untuk input baru.')
            ->visible(fn (Model $record) => (bool) $record->getAttribute('aktif') !== $aktif && Gate::allows('update', $record))
            ->action(fn (Model $record, Action $action) => RunsDomainAction::run(
                $action,
                fn () => app(SetMasterDataAktif::class)->handle(auth()->user(), $record, $aktif),
                $aktif ? 'Data diaktifkan' : 'Data dinonaktifkan',
            ));
    }
}
