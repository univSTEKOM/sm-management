<?php

/*
 * Fase 2 / T-10 UI: master data resources and the settings page are Superadmin only
 * (AC-USR-02) and write through the Master domain actions (FR-MST-01/02).
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Filament\Admin\Pages\PengaturanAplikasi;
use App\Filament\Admin\Resources\Master\Cabangs\Pages\ManageCabangs;
use App\Filament\Admin\Resources\Master\KategoriPelatihans\Pages\ManageKategoriPelatihans;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\Fixtures;

const MASTER_PAGES = [
    'cabang' => '/admin/master/cabang',
    'kabupaten/kota' => '/admin/master/kabupaten-kota',
    'kategori' => '/admin/master/kategori-pelatihan',
    'jenis' => '/admin/master/jenis-kegiatan',
    'metode' => '/admin/master/metode-kegiatan',
    'pengaturan' => '/admin/pengaturan',
];

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    $this->sa = User::factory()->superadmin()->create();
});

test('superadmin opens every master page and sees the navigation group', function (string $path) {
    $this->actingAs($this->sa)->get($path)->assertOk();
})->with(MASTER_PAGES);

test('AC-USR-02 admin pusat and admin cabang are refused (403) on master pages', function (string $role, string $path) {
    $user = $role === 'admin_pusat' ? User::factory()->adminPusat()->create() : User::factory()->adminCabang()->create();

    $this->actingAs($user)->get($path)->assertForbidden();
})->with(['admin_pusat', 'admin_cabang'])->with(MASTER_PAGES);

test('AC-USR-02 mentors cannot reach master pages on the admin panel', function () {
    $this->actingAs(User::factory()->mentor()->create())->get('/admin/master/cabang')->assertForbidden();
});

test('master and account menus are hidden from admin pusat', function () {
    $this->actingAs(User::factory()->adminPusat()->create())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('/admin/master/cabang', false)
        ->assertDontSee('/admin/pengguna', false)
        ->assertDontSee('/admin/pengaturan', false);
});

test('superadmin creates, edits and deactivates a branch through the resource with audit', function () {
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageCabangs::class)
        ->callAction('create', data: ['kode' => 'PWT', 'nama' => 'Cabang Purwokerto', 'alamat' => 'Jl. Jenderal Soedirman'])
        ->assertHasNoActionErrors();

    $cabang = Cabang::where('kode', 'PWT')->sole();
    expect($cabang->aktif)->toBeTrue();

    Livewire::test(ManageCabangs::class)
        ->callTableAction('edit', $cabang, data: ['kode' => 'PWT', 'nama' => 'Cabang Banyumas'])
        ->assertHasNoTableActionErrors()
        ->callTableAction('nonaktifkan', $cabang);

    expect($cabang->fresh())->nama->toBe('Cabang Banyumas')->aktif->toBeFalse();
    expect(AuditLog::whereIn('aksi', ['master.create', 'master.update', 'master.deactivate'])->where('entitas', 'cabang')->count())->toBe(3);
});

test('duplicate code is rejected in the form', function () {
    Cabang::factory()->create(['kode' => 'SMG']);
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageCabangs::class)
        ->callAction('create', data: ['kode' => 'SMG', 'nama' => 'Dobel'])
        ->assertHasActionErrors(['kode']);

    expect(Cabang::where('kode', 'SMG')->count())->toBe(1);
});

test('FR-MST-02 delete is offered only for unused data; used data offers deactivation', function () {
    $dipakai = Cabang::factory()->create();
    Fixtures::sekolah($dipakai);
    $kosong = Cabang::factory()->create();

    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageCabangs::class)
        ->assertTableActionHidden('hapus', $dipakai)
        ->assertTableActionVisible('nonaktifkan', $dipakai)
        ->assertTableActionVisible('hapus', $kosong)
        ->callTableAction('hapus', $kosong);

    expect(Cabang::find($kosong->id))->toBeNull()->and(Cabang::find($dipakai->id))->not->toBeNull();
});

test('a deactivated category can be reactivated from the table', function () {
    $kategori = KategoriPelatihan::where('nama', 'Barbershop')->sole();
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageKategoriPelatihans::class)
        ->callTableAction('nonaktifkan', $kategori)
        ->assertTableActionVisible('aktifkan', $kategori)
        ->callTableAction('aktifkan', $kategori);

    expect($kategori->fresh()->aktif)->toBeTrue();
});

test('superadmin saves the campus location from the settings page', function () {
    $this->actingAs($this->sa);
    Filament::setCurrentPanel('admin');

    Livewire::test(PengaturanAplikasi::class)
        ->assertSet('data.lokasi_kampus_pusat', 'Kampus Pusat')
        ->set('data.lokasi_kampus_pusat', 'Kampus Pusat Semarang')
        ->call('save')
        ->assertHasNoErrors();

    expect(AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT))->toBe('Kampus Pusat Semarang');
});

test('the settings save method itself refuses non-superadmins', function () {
    $this->actingAs(User::factory()->adminPusat()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(PengaturanAplikasi::class)->assertForbidden();
    expect(AppSetting::get(AppSetting::LOKASI_KAMPUS_PUSAT))->toBe('Kampus Pusat');
});

test('inactive master data is not offered when registering a school', function () {
    $nonaktif = Cabang::factory()->create(['nama' => 'Cabang Tutup', 'aktif' => false]);

    $this->actingAs(User::factory()->adminPusat()->create())
        ->get('/admin/sekolah/create')
        ->assertOk()
        ->assertDontSee('Cabang Tutup');

    expect($nonaktif->fresh()->aktif)->toBeFalse();
});
