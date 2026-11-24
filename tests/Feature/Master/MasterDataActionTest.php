<?php

/*
 * Fase 2 / T-10: master data actions (FR-MST-01, FR-MST-02, BR-USR-02, AC-USR-02).
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Master\Actions\DeleteMasterData;
use App\Domain\Master\Actions\SaveMasterData;
use App\Domain\Master\Actions\SetMasterDataAktif;
use App\Domain\Master\Actions\UpdateAppSetting;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Master\Support\MasterRegistry;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->sa = User::factory()->superadmin()->create();
});

test('BR-USR-03 seeder provides 35 kabupaten/kota, 6 kategori, jenis and metode, idempotently', function () {
    $this->seed(MasterDataSeeder::class);
    $this->seed(MasterDataSeeder::class);

    expect(KabupatenKota::count())->toBe(35)
        ->and(KategoriPelatihan::count())->toBe(6)
        ->and(JenisKegiatan::pluck('nama')->sort()->values()->all())->toBe(['Pelatihan', 'Pendampingan'])
        ->and(MetodeKegiatan::pluck('nama')->sort()->values()->all())->toBe(['Offline', 'Online'])
        ->and(AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT))->toBe('Kampus Pusat');
});

test('FR-MST-01 superadmin creates and updates every master type with an audit entry', function (string $type, array $create, array $update) {
    $record = app(SaveMasterData::class)->handle($this->sa, $type, $create);

    expect($record->aktif)->toBeTrue();
    expect(AuditLog::where('aksi', 'master.create')->where('entitas_id', (string) $record->getKey())->exists())->toBeTrue();

    $updated = app(SaveMasterData::class)->handle($this->sa, $type, $update, $record);

    foreach ($update as $field => $value) {
        expect($updated->fresh()->getAttribute($field))->toBe($value);
    }

    $audit = AuditLog::where('aksi', 'master.update')->latest('id')->first();
    expect($audit->sebelum)->toMatchArray($create)->and($audit->sesudah)->toMatchArray($update);
})->with([
    'cabang' => [Cabang::class, ['kode' => 'SMG', 'nama' => 'Cabang Semarang', 'alamat' => null], ['kode' => 'SMG', 'nama' => 'Cabang Semarang Raya', 'alamat' => 'Jl. Pemuda']],
    'kabupaten' => [KabupatenKota::class, ['kode' => '3399', 'nama' => 'Kabupaten Uji'], ['kode' => '3399', 'nama' => 'Kabupaten Uji Coba']],
    'kategori' => [KategoriPelatihan::class, ['nama' => 'Kategori Baru', 'deskripsi' => null], ['nama' => 'Kategori Baru', 'deskripsi' => 'Deskripsi']],
    'jenis' => [JenisKegiatan::class, ['nama' => 'Workshop'], ['nama' => 'Workshop Lanjut']],
    'metode' => [MetodeKegiatan::class, ['nama' => 'Hybrid', 'butuh_lokasi' => true, 'butuh_tautan' => false], ['nama' => 'Hybrid', 'butuh_lokasi' => true, 'butuh_tautan' => true]],
]);

test('AC-USR-02 admin pusat, admin cabang and mentor cannot manage master data', function (string $role) {
    $actor = match ($role) {
        'admin_pusat' => User::factory()->adminPusat()->create(),
        'admin_cabang' => User::factory()->adminCabang()->create(),
        'mentor' => User::factory()->mentor()->create(),
    };
    $cabang = Cabang::factory()->create();

    expect(fn () => app(SaveMasterData::class)->handle($actor, Cabang::class, ['kode' => 'X', 'nama' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveMasterData::class)->handle($actor, Cabang::class, ['nama' => 'Ubah'], $cabang))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetMasterDataAktif::class)->handle($actor, $cabang, false))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteMasterData::class)->handle($actor, $cabang))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateAppSetting::class)->handle($actor, AppSetting::LOKASI_KAMPUS_PUSAT, 'Lain'))->toThrow(AuthorizationException::class);

    expect($cabang->fresh()->nama)->not->toBe('Ubah')->and($cabang->fresh()->aktif)->toBeTrue();
})->with(['admin_pusat', 'admin_cabang', 'mentor']);

test('an inactive superadmin cannot manage master data', function () {
    $inactive = User::factory()->superadmin()->nonaktif()->create();

    expect(fn () => app(SaveMasterData::class)->handle($inactive, Cabang::class, ['kode' => 'X', 'nama' => 'X']))
        ->toThrow(AuthorizationException::class);
});

test('master codes and names stay unique', function () {
    Cabang::factory()->create(['kode' => 'SLO']);
    KategoriPelatihan::create(['nama' => 'Barbershop']);

    expect(fn () => app(SaveMasterData::class)->handle($this->sa, Cabang::class, ['kode' => 'SLO', 'nama' => 'Dobel']))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMasterData::class)->handle($this->sa, KategoriPelatihan::class, ['nama' => ' Barbershop ']))->toThrow(ValidationException::class);
});

test('FR-MST-02 used master data cannot be deleted, only deactivated', function () {
    $cabang = Cabang::factory()->create();
    $sekolah = Fixtures::sekolah($cabang);
    $kabupaten = $sekolah->kabupatenKota;

    foreach ([$cabang, $kabupaten] as $record) {
        expect(MasterRegistry::isUsed($record))->toBeTrue();
        expect(fn () => app(DeleteMasterData::class)->handle($this->sa, $record))->toThrow(ValidationException::class);
        expect($record->fresh())->not->toBeNull();

        app(SetMasterDataAktif::class)->handle($this->sa, $record, false);
        expect($record->fresh()->aktif)->toBeFalse();
    }

    // Existing data keeps its reference.
    expect($sekolah->fresh()->cabang_id)->toBe($cabang->id);
    expect(AuditLog::where('aksi', 'master.deactivate')->count())->toBe(2);
});

test('FR-MST-02 used jenis and metode (referenced by kegiatan) cannot be deleted', function () {
    $mentor = User::factory()->mentor()->create();
    $kegiatan = Fixtures::kegiatan(Fixtures::sekolah(Cabang::factory()->create(), $mentor), $mentor);

    foreach ([JenisKegiatan::find($kegiatan->jenis_kegiatan_id), MetodeKegiatan::find($kegiatan->metode_id)] as $record) {
        expect(fn () => app(DeleteMasterData::class)->handle($this->sa, $record))->toThrow(ValidationException::class);
    }
});

test('FR-MST-02 an admin cabang account counts as usage of its branch', function () {
    $cabang = Cabang::factory()->create();
    User::factory()->adminCabang($cabang)->create();

    expect(fn () => app(DeleteMasterData::class)->handle($this->sa, $cabang))->toThrow(ValidationException::class);
});

test('unused master data can be deleted by superadmin with an audit snapshot', function () {
    $kategori = KategoriPelatihan::create(['nama' => 'Belum Dipakai']);

    app(DeleteMasterData::class)->handle($this->sa, $kategori);

    expect(KategoriPelatihan::find($kategori->id))->toBeNull();
    $audit = AuditLog::where('aksi', 'master.delete')->first();
    expect($audit->sebelum['nama'])->toBe('Belum Dipakai')->and($audit->entitas)->toBe('kategori_pelatihan');
});

test('deactivated master data can be reactivated, and repeating the same state is refused', function () {
    $metode = MetodeKegiatan::create(['nama' => 'Hybrid']);

    app(SetMasterDataAktif::class)->handle($this->sa, $metode, false);
    expect(fn () => app(SetMasterDataAktif::class)->handle($this->sa, $metode->fresh(), false))->toThrow(ValidationException::class);

    app(SetMasterDataAktif::class)->handle($this->sa, $metode->fresh(), true);
    expect($metode->fresh()->aktif)->toBeTrue()
        ->and(AuditLog::where('aksi', 'master.activate')->count())->toBe(1);
});

test('superadmin updates the campus location setting with an audit entry', function () {
    $this->seed(MasterDataSeeder::class);

    app(UpdateAppSetting::class)->handle($this->sa, AppSetting::LOKASI_KAMPUS_PUSAT, ' Kampus Pusat, Jl. Majapahit 605 ');

    expect(AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT))->toBe('Kampus Pusat, Jl. Majapahit 605');
    $audit = AuditLog::where('aksi', 'setting.update')->first();
    expect($audit->sebelum)->toBe(['value' => 'Kampus Pusat'])
        ->and($audit->sesudah)->toBe(['value' => 'Kampus Pusat, Jl. Majapahit 605']);

    expect(fn () => app(UpdateAppSetting::class)->handle($this->sa, AppSetting::LOKASI_KAMPUS_PUSAT, ''))->toThrow(ValidationException::class);
    expect(fn () => app(UpdateAppSetting::class)->handle($this->sa, 'tidak_dikenal', 'x'))->toThrow(InvalidArgumentException::class);
});
