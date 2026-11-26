<?php

namespace App\Domain\Jadwal\Support;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Support\Theme\BrandPalette;

/**
 * Maps a kegiatan to a FullCalendar event object. Colour encodes the stage (Awal red,
 * Tindak Lanjut blue); the status is always spelled out as a label, and a cancelled
 * activity stays on the calendar with a distinct marker (class `kal-batal`).
 */
final class KalenderEvent
{
    public const WARNA_AWAL = BrandPalette::TAHAP_AWAL;

    public const WARNA_TL = BrandPalette::TAHAP_TINDAK_LANJUT;

    public const WARNA_BATAL = BrandPalette::DIBATALKAN;

    /** @return array<string, mixed> */
    public static function from(Kegiatan $kegiatan): array
    {
        $tanggal = $kegiatan->tanggal->toDateString();
        $batal = $kegiatan->status === StatusKegiatan::Dibatalkan;
        $warna = $batal ? self::WARNA_BATAL : ($kegiatan->tahap === Tahap::Awal ? self::WARNA_AWAL : self::WARNA_TL);

        return [
            'id' => (string) $kegiatan->id,
            'title' => $kegiatan->sekolah->nama,
            'start' => $tanggal.'T'.substr((string) $kegiatan->waktu_mulai, 0, 8),
            'end' => $tanggal.'T'.substr((string) $kegiatan->waktu_selesai, 0, 8),
            'backgroundColor' => $warna,
            'borderColor' => $warna,
            'classNames' => $batal ? ['kal-'.$kegiatan->tahap->value, 'kal-batal'] : ['kal-'.$kegiatan->tahap->value],
            'extendedProps' => [
                'tahap' => $kegiatan->tahap->label(),
                'sesi' => $kegiatan->nama_sesi,
                'mentor' => $kegiatan->mentorPelaksana?->name,
                'status' => $kegiatan->status->label(),
                'statusKey' => $kegiatan->status->value,
            ],
        ];
    }
}
