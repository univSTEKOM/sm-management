<?php

namespace App\Filament\Admin\Resources\Sekolahs\Tables;

use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\StatusSertifikat;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Support\ScopedOptions;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Daftar Sekolah Mitra" (PRD 13.2, UI brief 23 Nov 2026). Every relation and count is
 * loaded up front (withRingkasan + eager loads), so a page of rows costs a fixed number of
 * queries. Filters combine with AND (AC-SCH-05).
 */
class SekolahsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(Sekolah::ringkasanCounts())
                ->with(['cabang', 'kabupatenKota', 'penugasanAktif.mentor', 'narahubungUtama', 'sertifikat']))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('no')->label('No')->rowIndex(),
                TextColumn::make('nama')->label('Nama sekolah')->searchable()->sortable()->wrap()
                    ->description(fn (Sekolah $record) => $record->kabupatenKota->nama),
                TextColumn::make('jenjang')->badge()->color(fn (Jenjang $state) => $state->color()),
                TextColumn::make('cabang.nama')->label('Cabang')->toggleable(),
                TextColumn::make('mou')->label('MOU')->badge()
                    ->state(fn (Sekolah $record) => $record->isMou() ? 'Sudah MOU' : 'Belum MOU')
                    ->color(fn (Sekolah $record) => $record->isMou() ? 'success' : 'gray'),
                TextColumn::make('status_pelatihan')->label('Status pelatihan')->badge()
                    ->state(fn (Sekolah $record) => $record->statusPelatihan()->label())
                    ->color(fn (Sekolah $record) => $record->statusPelatihan()->color()),
                TextColumn::make('posisi_tahap')->label('Posisi tahap')->badge()->toggleable()
                    ->formatStateUsing(fn (PosisiTahap $state) => $state->label())
                    ->color(fn (PosisiTahap $state) => $state->color())
                    ->description(fn (Sekolah $record) => $record->progresTl()),
                TextColumn::make('penugasanAktif.mentor.name')->label('Penanggung jawab')->placeholder('Belum ditugaskan'),
                TextColumn::make('narahubungUtama.nama')->label('Narahubung')->placeholder('-')
                    ->description(fn (Sekolah $record) => collect([$record->narahubungUtama?->jabatan, $record->narahubungUtama?->nomor_wa])->filter()->implode(' · ') ?: null)
                    ->url(fn (Sekolah $record) => $record->narahubungUtama?->waUrl(), shouldOpenInNewTab: true),
                TextColumn::make('sertifikat_awal')->label('Sertifikat Awal')->badge()
                    ->state(fn (Sekolah $record) => $record->statusSertifikat(Tahap::Awal)->label())
                    ->color(fn (Sekolah $record) => $record->statusSertifikat(Tahap::Awal)->color()),
                TextColumn::make('sertifikat_tl')->label('Sertifikat TL')->badge()
                    ->state(fn (Sekolah $record) => $record->statusSertifikat(Tahap::TindakLanjut)->label())
                    ->color(fn (Sekolah $record) => $record->statusSertifikat(Tahap::TindakLanjut)->color()),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => ScopedOptions::cabang(auth()->user())),
                SelectFilter::make('kabupaten_kota_id')
                    ->label('Kabupaten/Kota')
                    ->options(fn () => KabupatenKota::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('jenjang')
                    ->options(collect(Jenjang::cases())->mapWithKeys(fn (Jenjang $j) => [$j->value => $j->value])->all()),
                SelectFilter::make('posisi_tahap')
                    ->label('Posisi tahap')
                    ->options(collect(PosisiTahap::cases())->mapWithKeys(fn (PosisiTahap $p) => [$p->value => $p->label()])->all()),
                SelectFilter::make('status_pelatihan')
                    ->label('Status pelatihan')
                    ->options(collect(StatusPelatihan::cases())->mapWithKeys(fn (StatusPelatihan $s) => [$s->value => $s->label()])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereStatusPelatihan(StatusPelatihan::from($data['value']))
                        : $query),
                SelectFilter::make('mentor')
                    ->label('Mentor')
                    ->options(fn () => User::query()->where('role', Role::Mentor->value)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('penugasanAktif', fn (Builder $q) => $q->where('mentor_id', $data['value']))
                        : $query),
                SelectFilter::make('kategori')
                    ->label('Kategori pelatihan')
                    ->options(fn () => KategoriPelatihan::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('kategori', fn (Builder $q) => $q->whereKey($data['value']))
                        : $query),
                TernaryFilter::make('mou')
                    ->label('MOU')
                    ->trueLabel('Sudah MOU')
                    ->falseLabel('Belum MOU')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('mou_dokumen_path'),
                        false: fn (Builder $q) => $q->whereNull('mou_dokumen_path'),
                    ),
                SelectFilter::make('sertifikat')
                    ->label('Sertifikat')
                    ->options([
                        'awal:'.StatusSertifikat::MenungguPenerbitan->value => 'Awal: Menunggu Penerbitan',
                        'awal:'.StatusSertifikat::Tersedia->value => 'Awal: Tersedia',
                        'tl:'.StatusSertifikat::MenungguPenerbitan->value => 'TL: Menunggu Penerbitan',
                        'tl:'.StatusSertifikat::Tersedia->value => 'TL: Tersedia',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        [$tahap, $status] = explode(':', (string) $data['value'], 2);
                        $tahap = $tahap === 'awal' ? Tahap::Awal : Tahap::TindakLanjut;

                        return $query->whereHas('sertifikat', fn (Builder $q) => $q->where('tahap', $tahap->value)
                            ->when($status === StatusSertifikat::Tersedia->value, fn (Builder $q) => $q->whereNotNull('link'), fn (Builder $q) => $q->whereNull('link')));
                    }),
                TernaryFilter::make('arsip')
                    ->label('Arsip')
                    ->placeholder('Hanya aktif')
                    ->trueLabel('Hanya arsip')
                    ->falseLabel('Semua')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('diarsipkan_at'),
                        false: fn (Builder $q) => $q,
                        blank: fn (Builder $q) => $q->whereNull('diarsipkan_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
                EditAction::make(),
            ])
            ->recordUrl(fn (Sekolah $record) => route('filament.admin.resources.sekolah.view', $record))
            ->toolbarActions([]);
    }
}
