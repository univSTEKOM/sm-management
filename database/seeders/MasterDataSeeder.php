<?php

namespace Database\Seeders;

use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Master\Models\MetodeKegiatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Reference data required in every environment (PRD 13.10). Idempotent. */
class MasterDataSeeder extends Seeder
{
    /** 35 kabupaten/kota Jawa Tengah with BPS codes. */
    private const KABUPATEN_KOTA = [
        '3301' => 'Kabupaten Cilacap', '3302' => 'Kabupaten Banyumas', '3303' => 'Kabupaten Purbalingga',
        '3304' => 'Kabupaten Banjarnegara', '3305' => 'Kabupaten Kebumen', '3306' => 'Kabupaten Purworejo',
        '3307' => 'Kabupaten Wonosobo', '3308' => 'Kabupaten Magelang', '3309' => 'Kabupaten Boyolali',
        '3310' => 'Kabupaten Klaten', '3311' => 'Kabupaten Sukoharjo', '3312' => 'Kabupaten Wonogiri',
        '3313' => 'Kabupaten Karanganyar', '3314' => 'Kabupaten Sragen', '3315' => 'Kabupaten Grobogan',
        '3316' => 'Kabupaten Blora', '3317' => 'Kabupaten Rembang', '3318' => 'Kabupaten Pati',
        '3319' => 'Kabupaten Kudus', '3320' => 'Kabupaten Jepara', '3321' => 'Kabupaten Demak',
        '3322' => 'Kabupaten Semarang', '3323' => 'Kabupaten Temanggung', '3324' => 'Kabupaten Kendal',
        '3325' => 'Kabupaten Batang', '3326' => 'Kabupaten Pekalongan', '3327' => 'Kabupaten Pemalang',
        '3328' => 'Kabupaten Tegal', '3329' => 'Kabupaten Brebes', '3371' => 'Kota Magelang',
        '3372' => 'Kota Surakarta', '3373' => 'Kota Salatiga', '3374' => 'Kota Semarang',
        '3375' => 'Kota Pekalongan', '3376' => 'Kota Tegal',
    ];

    /** PRD section 3 (decision). */
    private const KATEGORI = [
        'Jasa Layanan Bisnis',
        'Konten Kreator, Live Sales, Musik AI + DJ',
        'Akuntan Bisnis dan Perpajakan',
        'Pengacara Bisnis',
        'Arsitek Kontraktor Sipil',
        'Barbershop',
    ];

    public function run(): void
    {
        foreach (self::KABUPATEN_KOTA as $kode => $nama) {
            KabupatenKota::updateOrCreate(['kode' => (string) $kode], ['nama' => $nama]);
        }

        foreach (self::KATEGORI as $nama) {
            KategoriPelatihan::firstOrCreate(['nama' => $nama]);
        }

        // PD-19 (decision): Pelatihan, Pendampingan. Methods: Online, Offline.
        foreach (['Pelatihan', 'Pendampingan'] as $nama) {
            JenisKegiatan::firstOrCreate(['nama' => $nama]);
        }

        MetodeKegiatan::updateOrCreate(['nama' => 'Online'], ['butuh_tautan' => true, 'butuh_lokasi' => false]);
        MetodeKegiatan::updateOrCreate(['nama' => 'Offline'], ['butuh_lokasi' => true, 'butuh_tautan' => false]);

        // The exact campus address is not confirmed yet; Superadmin edits it.
        DB::table('app_settings')->insertOrIgnore([
            'key' => 'lokasi_kampus_pusat',
            'value' => json_encode('Kampus Pusat'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
