<?php

namespace Database\Factories;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kegiatan>
 */
class KegiatanFactory extends Factory
{
    protected $model = Kegiatan::class;

    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahap' => Tahap::TindakLanjut,
            'urutan' => fake()->unique()->numberBetween(1, 1000000),
            'nama_sesi' => 'Sesi',
            'jenis_kegiatan_id' => fn () => JenisKegiatan::firstOrCreate(['nama' => 'Pelatihan'])->id,
            'metode_id' => fn () => MetodeKegiatan::firstOrCreate(['nama' => 'Online'], ['butuh_tautan' => true])->id,
            'tanggal' => now()->addWeek()->toDateString(),
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '11:00',
            'tautan_pertemuan' => 'https://meet.example.test/abc',
            'mentor_pelaksana_id' => User::factory()->mentor(),
            'status' => StatusKegiatan::BelumDimulai,
            'created_by' => User::factory()->adminPusat(),
        ];
    }
}
