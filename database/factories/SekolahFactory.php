<?php

namespace Database\Factories;

use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sekolah>
 */
class SekolahFactory extends Factory
{
    protected $model = Sekolah::class;

    public function definition(): array
    {
        $nama = 'SMAN '.fake()->unique()->numberBetween(1, 99999).' '.fake()->city();

        return [
            'nama' => $nama,
            'nama_normal' => NamaSekolahNormalizer::normalize($nama),
            'jenjang' => Jenjang::SMA,
            'kabupaten_kota_id' => KabupatenKota::factory(),
            'cabang_id' => Cabang::factory(),
            'posisi_tahap' => PosisiTahap::CalonMitra,
            'created_by' => User::factory()->superadmin(),
        ];
    }
}
