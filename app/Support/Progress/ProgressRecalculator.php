<?php

namespace App\Support\Progress;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Rangkaian\Models\RangkaianTlRiwayat;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use Illuminate\Support\Collection;

/**
 * Recomputes the derived state of one school (PRD 15.3–15.5, BR-TL-06/12,
 * FR-CRT-01). Call it inside the same transaction as the change that caused it.
 * Cancelled activities never count as active or pending.
 */
final class ProgressRecalculator
{
    public static function forSekolah(Sekolah $sekolah): ProgressResult
    {
        /** @var Collection<int, Kegiatan> $kegiatan */
        $kegiatan = $sekolah->kegiatan()->lockForUpdate()->get();

        $awalSelesai = $kegiatan->first(fn (Kegiatan $k) => $k->tahap === Tahap::Awal && $k->status === StatusKegiatan::Selesai);
        $tl = $kegiatan->filter(fn (Kegiatan $k) => $k->tahap === Tahap::TindakLanjut);

        $sesiTerakhirDibatalkan = self::releaseCancelledLastSession($sekolah, $tl);

        $tlAktif = $tl->filter(fn (Kegiatan $k) => $k->status !== StatusKegiatan::Dibatalkan);
        $sesiTerakhir = $tlAktif->first(fn (Kegiatan $k) => $k->is_sesi_terakhir);

        $rangkaianSelesai = $sesiTerakhir !== null
            && $tlAktif->every(fn (Kegiatan $k) => $k->status === StatusKegiatan::Selesai);

        $posisi = match (true) {
            $awalSelesai === null => PosisiTahap::CalonMitra,
            $tlAktif->isEmpty() => PosisiTahap::MitraBelumTl,
            $rangkaianSelesai => PosisiTahap::SelesaiProgram,
            default => PosisiTahap::MitraTl,
        };

        $wasMitra = $sekolah->tanggal_mitra !== null;
        $wasSelesai = $sekolah->tl_selesai_at !== null;

        $sekolah->posisi_tahap = $posisi;
        $sekolah->tanggal_mitra = $awalSelesai?->tanggal;
        $sekolah->tl_selesai_at = $rangkaianSelesai ? ($sekolah->tl_selesai_at ?? now()) : null;
        $sekolah->saveQuietly();

        if ($awalSelesai !== null) {
            Sertifikat::firstOrCreate(
                ['sekolah_id' => $sekolah->id, 'tahap' => Tahap::Awal->value],
                ['kegiatan_acuan_id' => $awalSelesai->id],
            );
        }

        if ($rangkaianSelesai) {
            $sertifikat = Sertifikat::firstOrCreate(
                ['sekolah_id' => $sekolah->id, 'tahap' => Tahap::TindakLanjut->value],
                ['kegiatan_acuan_id' => $sesiTerakhir->id],
            );

            if ((int) $sertifikat->kegiatan_acuan_id !== (int) $sesiTerakhir->id) {
                $sertifikat->update(['kegiatan_acuan_id' => $sesiTerakhir->id]);
            }

            if (! $wasSelesai) {
                RangkaianTlRiwayat::create([
                    'sekolah_id' => $sekolah->id,
                    'aksi' => 'selesai_otomatis',
                    'kegiatan_id' => $sesiTerakhir->id,
                ]);
            }
        }

        return new ProgressResult(
            menjadiMitra: ! $wasMitra && $awalSelesai !== null,
            rangkaianSelesai: ! $wasSelesai && $rangkaianSelesai,
            sesiTerakhirDibatalkan: $sesiTerakhirDibatalkan,
        );
    }

    /** BR-TL-12 / FR-JTL-10: a cancelled last session no longer counts as the last session. */
    private static function releaseCancelledLastSession(Sekolah $sekolah, Collection $tl): bool
    {
        $cancelled = $tl->first(fn (Kegiatan $k) => $k->is_sesi_terakhir && $k->status === StatusKegiatan::Dibatalkan);

        if ($cancelled === null) {
            return false;
        }

        $cancelled->is_sesi_terakhir = false;
        $cancelled->saveQuietly();

        RangkaianTlRiwayat::create([
            'sekolah_id' => $sekolah->id,
            'aksi' => 'sesi_terakhir_dibatalkan',
            'kegiatan_id' => $cancelled->id,
        ]);

        return true;
    }
}
