<?php

namespace App\Filament\Admin\Resources\Sekolahs\RelationManagers;

use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Support\JadwalActions;
use App\Filament\Support\RescheduleFormSchema;
use App\Filament\Support\RunsDomainAction;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class KegiatanRelationManager extends RelationManager
{
    protected static string $relationship = 'kegiatan';

    protected static ?string $title = 'Kegiatan';

    /** Set by the stage-specific subclasses so an activity shows in exactly one tab (AC-SCH-09). */
    protected static ?Tahap $tahap = null;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['metode', 'mentorPelaksana'])
                ->when(static::$tahap, fn ($q, Tahap $tahap) => $q->where('tahap', $tahap->value))
                ->orderBy('tahap')->orderBy('urutan'))
            ->columns([
                TextColumn::make('tahap')->badge()->formatStateUsing(fn (Tahap $state) => $state->label())->color(fn (Tahap $state) => $state->color()),
                TextColumn::make('nama_sesi')->label('Sesi'),
                TextColumn::make('tanggal')->date('d M Y'),
                TextColumn::make('waktu_mulai')->label('Waktu')->formatStateUsing(fn ($state, Kegiatan $record) => substr((string) $state, 0, 5).'–'.substr((string) $record->waktu_selesai, 0, 5)),
                TextColumn::make('metode.nama')->label('Metode'),
                TextColumn::make('mentorPelaksana.name')->label('Mentor pelaksana'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusKegiatan $state) => $state->label())->color(fn (StatusKegiatan $state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('tahap')->visible(static::$tahap === null)->options([
                    Tahap::Awal->value => Tahap::Awal->label(),
                    Tahap::TindakLanjut->value => Tahap::TindakLanjut->label(),
                ]),
            ])
            ->headerActions($this->tableHeaderActions())
            ->recordActions([
                Action::make('reschedule')
                    ->label('Jadwal Ulang')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn (Kegiatan $record) => Gate::allows('reschedule', $record))
                    ->fillForm(fn (Kegiatan $record) => RescheduleFormSchema::fill($record))
                    ->schema(RescheduleFormSchema::components())
                    ->action(function (Action $action, Kegiatan $record, array $data): void {
                        RunsDomainAction::run($action, function () use ($record, $data) {
                            $input = RescheduleFormSchema::input($data);
                            app(RescheduleKegiatan::class)->handle(auth()->user(), $record, $input, (string) ($input['alasan'] ?? ''), isset($input['version']) ? (int) $input['version'] : null);
                        }, 'Jadwal diperbarui');
                    }),
                JadwalActions::ubahDetail(),
                JadwalActions::tunjukPelaksana(),
                JadwalActions::riwayat(),
            ]);
    }

    /** Stage-specific create actions; the base manager (all stages) has none. @return array<int, Action> */
    protected function tableHeaderActions(): array
    {
        return [];
    }
}
