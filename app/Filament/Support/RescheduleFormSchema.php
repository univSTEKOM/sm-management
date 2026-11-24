<?php

namespace App\Filament\Support;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\Tahap;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;

/** Shared "Jadwal Ulang" form for both panels. Rules are enforced in RescheduleKegiatan. */
final class RescheduleFormSchema
{
    /** @return array<int, mixed> */
    public static function components(): array
    {
        return [
            DatePicker::make('tanggal')->required()->native(false)->displayFormat('d M Y'),
            TimePicker::make('waktu_mulai')->label('Mulai')->seconds(false)->required(),
            TimePicker::make('waktu_selesai')->label('Selesai')->seconds(false)->required(),
            Select::make('metode_id')
                ->label('Metode')
                ->options(fn () => MetodeKegiatan::query()->where('aktif', true)->pluck('nama', 'id')->all())
                ->visible(fn (?Kegiatan $record) => auth()->user()?->role !== Role::Mentor && $record?->tahap !== Tahap::Awal),
            TextInput::make('lokasi')->maxLength(1000),
            TextInput::make('tautan_pertemuan')->label('Tautan pertemuan')->url()->maxLength(1000),
            Textarea::make('alasan')->label('Alasan perubahan')->required(),
            Hidden::make('version'),
        ];
    }

    /** @return array<string, mixed> */
    public static function fill(Kegiatan $kegiatan): array
    {
        return [
            'tanggal' => $kegiatan->tanggal->toDateString(),
            'waktu_mulai' => substr((string) $kegiatan->waktu_mulai, 0, 5),
            'waktu_selesai' => substr((string) $kegiatan->waktu_selesai, 0, 5),
            'metode_id' => $kegiatan->metode_id,
            'lokasi' => $kegiatan->lokasi,
            'tautan_pertemuan' => $kegiatan->tautan_pertemuan,
            'version' => $kegiatan->version,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function input(array $data): array
    {
        foreach (['waktu_mulai', 'waktu_selesai'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = substr((string) $data[$key], 0, 5);
            }
        }

        return $data;
    }
}
