<?php

namespace App\Filament\Widgets;

use App\Domain\Laporan\Models\LaporanRevisi;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * One school per row (never cards: the list can be long). Scope comes from the same query
 * the school resource uses, so a user never sees a row they could not open. Read-only.
 */
class SekolahMitraTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Daftar Sekolah Mitra';

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $detail = fn (Sekolah $record) => route("filament.{$panel}.resources.sekolah.view", $record);

        return $table
            ->heading('Daftar Sekolah Mitra')
            ->query(fn (): Builder => Sekolah::query()
                ->visibleTo($user)
                ->withCount(Sekolah::ringkasanCounts())
                ->addSelect(['update_mentor_at' => LaporanRevisi::query()
                    ->selectRaw('max(laporan_revisi.created_at)')
                    ->join('kegiatan', 'kegiatan.id', '=', 'laporan_revisi.kegiatan_id')
                    ->whereColumn('kegiatan.sekolah_id', 'sekolah.id')])
                ->with(['kabupatenKota', 'penugasanAktif.mentor', 'narahubungUtama', 'sertifikat']))
            ->defaultSort('nama')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->extraAttributes(['class' => 'dash-sekolah-table'])
            ->columns([
                TextColumn::make('nama')->label('Nama sekolah')->searchable()->sortable()->wrap(),
                TextColumn::make('jenjang')->badge()->color(fn (Jenjang $state) => $state->color()),
                TextColumn::make('kabupatenKota.nama')->label('Kabupaten/Kota')->wrap(),
                TextColumn::make('status_program')->label('Status program')->badge()
                    ->state(fn (Sekolah $record) => $record->statusPelatihan()->label())
                    ->color(fn (Sekolah $record) => $record->statusPelatihan()->color()),
                TextColumn::make('penugasanAktif.mentor.name')->label('Mentor PJ')->placeholder('Belum ditugaskan')->wrap(),
                TextColumn::make('narahubungUtama.nama')->label('Narahubung')->placeholder('-')->wrap()
                    ->url(fn (Sekolah $record) => $record->narahubungUtama?->waUrl(), shouldOpenInNewTab: true),
                TextColumn::make('sertifikat')->label('Sertifikat')
                    ->state(fn (Sekolah $record) => 'Awal: '.$record->statusSertifikat(Tahap::Awal)->label())
                    ->description(fn (Sekolah $record) => 'TL: '.$record->statusSertifikat(Tahap::TindakLanjut)->label())
                    ->extraAttributes(['class' => 'whitespace-nowrap']),
                TextColumn::make('mou')->label('Status MOU')->badge()
                    ->state(fn (Sekolah $record) => $record->isMou() ? 'Sudah MOU' : 'Belum MOU')
                    ->color(fn (Sekolah $record) => $record->isMou() ? 'success' : 'gray'),
                TextColumn::make('progres_tl')->label('Progres tindak lanjut')
                    ->state(fn (Sekolah $record) => $record->progresTl())->placeholder('-'),
                TextColumn::make('update_mentor_at')->label('Update mentor')->sortable()
                    ->dateTime('d M Y')->placeholder('Belum ada'),
            ])
            ->filters([
                SelectFilter::make('kabupaten_kota_id')->label('Kabupaten/Kota')
                    ->options(fn () => KabupatenKota::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('jenjang')
                    ->options(collect(Jenjang::cases())->mapWithKeys(fn (Jenjang $j) => [$j->value => $j->value])->all()),
                SelectFilter::make('status_program')->label('Status program')
                    ->options(collect(StatusPelatihan::cases())->mapWithKeys(fn (StatusPelatihan $s) => [$s->value => $s->label()])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereStatusPelatihan(StatusPelatihan::from($data['value']))
                        : $query),
                SelectFilter::make('mentor')->label('Mentor PJ')
                    ->options(fn () => User::query()->where('role', Role::Mentor->value)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('penugasanAktif', fn (Builder $q) => $q->where('mentor_id', $data['value']))
                        : $query),
                TernaryFilter::make('mou')->label('Status MOU')
                    ->trueLabel('Sudah MOU')->falseLabel('Belum MOU')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('mou_dokumen_path'),
                        false: fn (Builder $q) => $q->whereNull('mou_dokumen_path'),
                    ),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('diarsipkan_at'))
            ->searchPlaceholder('Cari nama sekolah')
            ->recordUrl($detail)
            ->recordActions([
                Action::make('detail')->label('Detail')->icon('heroicon-o-eye')->url($detail),
            ])
            ->emptyStateHeading('Belum ada sekolah');
    }
}
