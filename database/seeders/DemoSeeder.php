<?php

namespace Database\Seeders;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use App\Support\Progress\ProgressRecalculator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data for manual checks and Playwright. Refuses to run outside
 * local/testing. Every demo account uses the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder only runs in local or testing.');
        }

        $this->call(MasterDataSeeder::class);

        $semarang = Cabang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Cabang Semarang']);
        $solo = Cabang::firstOrCreate(['kode' => 'SLO'], ['nama' => 'Cabang Surakarta']);

        $sa = $this->user('superadmin@demo.test', 'Superadmin Demo', Role::Superadmin);
        $this->user('pusat@demo.test', 'Admin Pusat Demo', Role::AdminPusat);
        $this->user('cabang.semarang@demo.test', 'Admin Cabang Semarang', Role::AdminCabang, $semarang);
        $this->user('cabang.solo@demo.test', 'Admin Cabang Surakarta', Role::AdminCabang, $solo);
        $mentor = $this->user('mentor@demo.test', 'Mentor Demo', Role::Mentor);
        $mentorLain = $this->user('mentor2@demo.test', 'Mentor Lain', Role::Mentor);

        $online = MetodeKegiatan::where('nama', 'Online')->firstOrFail();
        $offline = MetodeKegiatan::where('nama', 'Offline')->firstOrFail();
        $pelatihan = JenisKegiatan::where('nama', 'Pelatihan')->firstOrFail();

        $smg = $this->sekolah('SMAN 1 Semarang', $semarang, KabupatenKota::where('kode', '3374')->firstOrFail(), $sa, $mentor);
        $slo = $this->sekolah('SMKN 2 Surakarta', $solo, KabupatenKota::where('kode', '3372')->firstOrFail(), $sa, $mentorLain);

        $this->kegiatan($smg, $mentor, $sa, Tahap::Awal, 1, 'Pelatihan Awal', $offline, $pelatihan, today()->subDays(2), StatusKegiatan::BelumDimulai, ['lokasi' => 'Kampus Pusat']);
        $this->kegiatan($smg, $mentor, $sa, Tahap::TindakLanjut, 1, 'Sesi 1', $online, $pelatihan, today()->addDays(3), StatusKegiatan::BelumDimulai, ['tautan_pertemuan' => 'https://meet.example.test/smg-1']);
        $this->kegiatan($smg, $mentor, $sa, Tahap::TindakLanjut, 2, 'Sesi 2', $online, $pelatihan, today()->addDays(10), StatusKegiatan::BelumDimulai, ['tautan_pertemuan' => 'https://meet.example.test/smg-2']);
        $this->mitraDemo($semarang, $solo, $sa, $mentorLain, $offline, $pelatihan);
        $this->kegiatan($slo, $mentorLain, $sa, Tahap::TindakLanjut, 1, 'Sesi 1', $online, $pelatihan, today()->addDays(4), StatusKegiatan::BelumDimulai, ['tautan_pertemuan' => 'https://meet.example.test/rahasia-solo']);
    }

    private function user(string $email, string $name, Role $role, ?Cabang $cabang = null): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'role' => $role,
            'cabang_id' => $cabang?->id,
            'aktif' => true,
            'wajib_ganti_password' => false,
        ]);
    }

    /**
     * Official partners (initial training finished long ago) with training categories, so the
     * klasemen has data. They belong to the second mentor so the first mentor's screens stay
     * unchanged. SMAN 1 Semarang is only a candidate: its category must not be counted.
     */
    private function mitraDemo(Cabang $semarang, Cabang $solo, User $sa, User $mentorLain, MetodeKegiatan $offline, JenisKegiatan $pelatihan): void
    {
        $kategori = fn (string $nama) => KategoriPelatihan::where('nama', $nama)->firstOrFail()->id;
        $jasa = $kategori('Jasa Layanan Bisnis');
        $barber = $kategori('Barbershop');
        $akuntan = $kategori('Akuntan Bisnis dan Perpajakan');

        $semarangKota = KabupatenKota::where('kode', '3374')->firstOrFail();
        $soloKota = KabupatenKota::where('kode', '3372')->firstOrFail();

        $mitra = [
            [$this->sekolah('SMAN 2 Semarang', $semarang, $semarangKota, $sa, $mentorLain), $mentorLain, [$jasa, $barber]],
            [$this->sekolah('SMKN 4 Semarang', $semarang, $semarangKota, $sa, $mentorLain), $mentorLain, [$jasa]],
            [$this->sekolah('SMAN 1 Surakarta', $solo, $soloKota, $sa, $mentorLain), $mentorLain, [$jasa, $akuntan]],
        ];

        foreach ($mitra as [$sekolah, $pelaksana, $kategoriIds]) {
            $this->kegiatan($sekolah, $pelaksana, $sa, Tahap::Awal, 1, 'Pelatihan Awal', $offline, $pelatihan, today()->subDays(120), StatusKegiatan::Selesai, ['lokasi' => 'Kampus Pusat']);
            $sekolah->kategori()->syncWithoutDetaching($kategoriIds);
            ProgressRecalculator::forSekolah($sekolah);
        }

        Sekolah::where('nama', 'SMAN 1 Semarang')->firstOrFail()->kategori()->syncWithoutDetaching([$barber]);
    }

    private function sekolah(string $nama, Cabang $cabang, KabupatenKota $kab, User $creator, User $mentor): Sekolah
    {
        $sekolah = Sekolah::firstOrCreate(
            ['nama_normal' => NamaSekolahNormalizer::normalize($nama), 'jenjang' => str_starts_with($nama, 'SMK') ? Jenjang::SMK : Jenjang::SMA, 'kabupaten_kota_id' => $kab->id],
            ['nama' => $nama, 'cabang_id' => $cabang->id, 'created_by' => $creator->id],
        );

        Narahubung::firstOrCreate(['sekolah_id' => $sekolah->id, 'is_utama' => true], ['nama' => 'Bapak Waka Kurikulum', 'nomor_wa' => '081200000000']);
        PenugasanMentor::firstOrCreate(['sekolah_id' => $sekolah->id, 'selesai' => null], ['mentor_id' => $mentor->id, 'ditugaskan_oleh' => $creator->id]);

        return $sekolah;
    }

    private function kegiatan(Sekolah $sekolah, User $mentor, User $creator, Tahap $tahap, int $urutan, string $nama, MetodeKegiatan $metode, JenisKegiatan $jenis, $tanggal, StatusKegiatan $status, array $extra): void
    {
        Kegiatan::firstOrCreate(['sekolah_id' => $sekolah->id, 'tahap' => $tahap, 'urutan' => $urutan], $extra + [
            'nama_sesi' => $nama,
            'jenis_kegiatan_id' => $jenis->id,
            'metode_id' => $metode->id,
            'tanggal' => $tanggal,
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '11:00',
            'mentor_pelaksana_id' => $mentor->id,
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }
}
