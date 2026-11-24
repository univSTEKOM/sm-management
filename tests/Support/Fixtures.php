<?php

namespace Tests\Support;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\Cabang;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;

/** Small builders shared by feature tests. */
final class Fixtures
{
    public static function sekolah(Cabang $cabang, ?User $mentor = null, array $attributes = []): Sekolah
    {
        if (isset($attributes['nama'])) {
            $attributes['nama_normal'] = NamaSekolahNormalizer::normalize($attributes['nama']);
        }

        $sekolah = Sekolah::factory()->create(['cabang_id' => $cabang->id] + $attributes);

        Narahubung::create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Narahubung '.$sekolah->id,
            'nomor_wa' => '0812000'.$sekolah->id,
            'is_utama' => true,
        ]);

        if ($mentor !== null) {
            self::assign($sekolah, $mentor);
        }

        return $sekolah;
    }

    public static function assign(Sekolah $sekolah, User $mentor): PenugasanMentor
    {
        return PenugasanMentor::create([
            'sekolah_id' => $sekolah->id,
            'mentor_id' => $mentor->id,
            'ditugaskan_oleh' => User::factory()->adminPusat()->create()->id,
        ]);
    }

    public static function kegiatan(Sekolah $sekolah, User $pelaksana, array $attributes = []): Kegiatan
    {
        static $urutan = 0;

        // Explicit attributes win over the defaults.
        return Kegiatan::factory()->create($attributes + [
            'sekolah_id' => $sekolah->id,
            'mentor_pelaksana_id' => $pelaksana->id,
            'tahap' => Tahap::TindakLanjut,
            'urutan' => ++$urutan,
            'nama_sesi' => 'Sesi '.$urutan,
        ]);
    }
}
