<?php

namespace App\Filament\Admin\Resources\Sekolahs\Tables;

use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Filament\Support\ScopedOptions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SekolahsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cabang', 'kabupatenKota', 'penugasanAktif.mentor', 'narahubungUtama']))
            ->columns([
                TextColumn::make('nama')->label('Nama sekolah')->searchable()->sortable(),
                TextColumn::make('jenjang')->badge(),
                TextColumn::make('kabupatenKota.nama')->label('Kabupaten/Kota')->sortable(),
                TextColumn::make('cabang.nama')->label('Cabang'),
                TextColumn::make('posisi_tahap')
                    ->label('Posisi tahap')
                    ->badge()
                    ->formatStateUsing(fn (PosisiTahap $state) => $state->label()),
                TextColumn::make('penugasanAktif.mentor.name')->label('Mentor')->placeholder('Belum ditugaskan'),
                TextColumn::make('narahubungUtama.nama')
                    ->label('Narahubung')
                    ->description(fn ($record) => $record->narahubungUtama?->nomor_wa),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => ScopedOptions::cabang(auth()->user())),
                SelectFilter::make('kabupaten_kota_id')
                    ->label('Kabupaten/Kota')
                    ->options(fn () => KabupatenKota::query()->orderBy('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('posisi_tahap')
                    ->label('Posisi tahap')
                    ->options(collect(PosisiTahap::cases())->mapWithKeys(fn (PosisiTahap $p) => [$p->value => $p->label()])->all()),
                SelectFilter::make('jenjang')
                    ->options(collect(Jenjang::cases())->mapWithKeys(fn (Jenjang $j) => [$j->value => $j->value])->all()),
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
