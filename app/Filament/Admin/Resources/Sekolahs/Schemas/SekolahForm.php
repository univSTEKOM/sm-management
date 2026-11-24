<?php

namespace App\Filament\Admin\Resources\Sekolahs\Schemas;

use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Sekolah\Actions\DuplicateSekolahGuard;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\Role;
use App\Filament\Support\ScopedOptions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class SekolahForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                Section::make('Identitas Sekolah')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama sekolah')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText(function (?string $state, string $operation): ?string {
                                if ($operation !== 'create' || blank($state)) {
                                    return null;
                                }

                                $similar = DuplicateSekolahGuard::similarNames($state);

                                return $similar === [] ? null : 'Nama mirip yang sudah terdaftar: '.implode(', ', $similar);
                            }),
                        Select::make('jenjang')
                            ->options(collect(Jenjang::cases())->mapWithKeys(fn (Jenjang $j) => [$j->value => $j->value])->all())
                            ->required(),
                        Select::make('kabupaten_kota_id')
                            ->label('Kabupaten/Kota')
                            ->options(fn (?Sekolah $record) => KabupatenKota::query()
                                ->where(fn ($q) => $q->where('aktif', true)->when($record, fn ($q) => $q->orWhere('id', $record->kabupaten_kota_id)))
                                ->orderBy('nama')->pluck('nama', 'id')->all())
                            ->searchable()
                            ->required(),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(fn () => ScopedOptions::cabang($user))
                            ->default($user?->role === Role::AdminCabang ? $user->cabang_id : null)
                            // BR-SCH-09/10: chosen at registration, read-only afterwards.
                            ->disabled(fn (string $operation) => $operation !== 'create' || $user?->role === Role::AdminCabang)
                            ->dehydrated(fn (string $operation) => $operation === 'create')
                            ->required(),
                        Textarea::make('alamat')->columnSpanFull(),
                        Select::make('kategori_ids')
                            ->label('Kategori pelatihan')
                            ->multiple()
                            ->options(fn () => KategoriPelatihan::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id')->all())
                            ->columnSpanFull(),
                        Hidden::make('version'),
                    ]),
                Section::make('Narahubung Utama')
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('narahubung_utama.nama')->label('Nama')->required(),
                        TextInput::make('narahubung_utama.nomor_wa')->label('Nomor WhatsApp')->tel()->required(),
                        TextInput::make('narahubung_utama.jabatan')->label('Jabatan'),
                        TextInput::make('narahubung_utama.email')->label('Email')->email(),
                    ]),
            ]);
    }

    /** @param array<string, mixed> $data */
    public static function payload(array $data): array
    {
        return Arr::except($data, ['version']);
    }
}
