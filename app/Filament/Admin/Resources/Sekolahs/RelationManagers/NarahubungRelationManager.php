<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Sekolah\Actions\ManageNarahubung;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\Sekolah;
use App\Filament\Support\RunsDomainAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Contacts of a school (BR-SCH-06, AC-SCH-12/16). Every change goes through
 * ManageNarahubung; nothing is deleted, only deactivated.
 */
class NarahubungRelationManager extends RelationManager
{
    protected static string $relationship = 'narahubung';

    protected static ?string $title = 'Narahubung';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** @return list<Component|Field> */
    private static function fields(): array
    {
        return [
            TextInput::make('nama')->required()->maxLength(255),
            TextInput::make('jabatan')->maxLength(255),
            TextInput::make('nomor_wa')->label('Nomor WhatsApp')->required()->tel()->maxLength(30),
            TextInput::make('email')->email()->maxLength(255),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('aktif')->orderByDesc('is_utama')->orderBy('nama'))
            ->columns([
                TextColumn::make('nama')->description(fn (Narahubung $record) => $record->jabatan),
                TextColumn::make('nomor_wa')->label('WhatsApp')
                    ->url(fn (Narahubung $record) => $record->waUrl(), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-chat-bubble-left-right'),
                TextColumn::make('email')->placeholder('-'),
                IconColumn::make('is_utama')->label('Utama')->boolean(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->default(true),
            ])
            ->headerActions([
                Action::make('addNarahubung')
                    ->label('Tambah Narahubung')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => Gate::allows('manageNarahubung', $this->getOwnerRecord()))
                    ->schema([...self::fields(), Toggle::make('utama')->label('Jadikan narahubung utama')])
                    ->action(function (Action $action, array $data) {
                        /** @var Sekolah $sekolah */
                        $sekolah = $this->getOwnerRecord();
                        RunsDomainAction::run($action, fn () => app(ManageNarahubung::class)
                            ->add(auth()->user(), $sekolah, $data, (bool) ($data['utama'] ?? false)), 'Narahubung ditambahkan');
                    }),
            ])
            ->recordActions([
                Action::make('editNarahubung')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Narahubung $record) => $record->aktif && Gate::allows('manageNarahubung', $this->getOwnerRecord()))
                    ->fillForm(fn (Narahubung $record) => $record->only([...Narahubung::FIELDS, 'version']))
                    ->schema([...self::fields(), Hidden::make('version')])
                    ->action(function (Action $action, Narahubung $record, array $data) {
                        RunsDomainAction::run($action, fn () => app(ManageNarahubung::class)
                            ->update(auth()->user(), $record, $data, isset($data['version']) ? (int) $data['version'] : null), 'Narahubung diperbarui');
                    }),
                Action::make('setUtama')
                    ->label('Jadikan Utama')
                    ->icon('heroicon-o-star')
                    ->requiresConfirmation()
                    ->visible(fn (Narahubung $record) => $record->aktif && ! $record->is_utama && Gate::allows('manageNarahubung', $this->getOwnerRecord()))
                    ->action(function (Action $action, Narahubung $record) {
                        RunsDomainAction::run($action, fn () => app(ManageNarahubung::class)->setUtama(auth()->user(), $record), 'Narahubung utama diganti');
                    }),
                Action::make('deactivateNarahubung')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Narahubung $record) => $record->aktif && ! $record->is_utama && Gate::allows('manageNarahubung', $this->getOwnerRecord()))
                    ->action(function (Action $action, Narahubung $record) {
                        RunsDomainAction::run($action, fn () => app(ManageNarahubung::class)->deactivate(auth()->user(), $record), 'Narahubung dinonaktifkan');
                    }),
            ]);
    }
}
