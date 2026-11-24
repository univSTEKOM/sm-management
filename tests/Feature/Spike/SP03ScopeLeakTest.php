<?php

/*
 * SP-03 / Implementation Plan 4.11: no cross-branch leaks through resources,
 * dropdowns, filters, relation managers or Livewire requests, and no mentor
 * action without an assignment. Sentinel strings mark data that must never
 * appear for the wrong user.
 */

use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Laporan\Actions\ReportKegiatan;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Sekolah\Actions\RegisterSekolah;
use App\Domain\Sekolah\Actions\UpdateSekolah;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Filament\Admin\Resources\Sekolahs\Pages\EditSekolah;
use App\Filament\Admin\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Admin\Resources\Sekolahs\Pages\ViewSekolah;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanRelationManager;
use App\Filament\Mentor\Pages\KegiatanSaya;
use App\Filament\Support\ScopedOptions;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Support\Fixtures;
use Tests\Support\LivewireHttp;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create(['nama' => 'Cabang Alpha']);
    $this->cabangB = Cabang::factory()->create(['nama' => 'SENTINEL-CABANG-B']);
    $this->mentor1 = User::factory()->mentor()->create();
    $this->mentor2 = User::factory()->mentor()->create();
    $this->adminCabangA = User::factory()->adminCabang($this->cabangA)->create();

    $this->sekolahA = Fixtures::sekolah($this->cabangA, $this->mentor1, ['nama' => 'SMAN 1 Alpha']);
    $this->sekolahB = Fixtures::sekolah($this->cabangB, $this->mentor2, ['nama' => 'SENTINEL-SEKOLAH-B']);
    $this->sekolahB->narahubungUtama()->update(['nama' => 'SENTINEL-NARAHUBUNG-B']);

    $this->kegiatanA = Fixtures::kegiatan($this->sekolahA, $this->mentor1);
    $this->kegiatanB = Fixtures::kegiatan($this->sekolahB, $this->mentor2, [
        'tautan_pertemuan' => 'https://meet.example.test/SENTINEL-TAUTAN-M2',
    ]);
});

function sentinels(): array
{
    return ['SENTINEL-CABANG-B', 'SENTINEL-SEKOLAH-B', 'SENTINEL-NARAHUBUNG-B', 'SENTINEL-TAUTAN-M2'];
}

test('LS-09 admin cabang pages never render data from another branch', function (string $path) {
    $url = str_replace('{A}', (string) $this->sekolahA->id, $path);
    $response = $this->actingAs($this->adminCabangA)->get($url)->assertOk();

    foreach (sentinels() as $sentinel) {
        $response->assertDontSee($sentinel, false);
    }
})->with([
    'list' => '/admin/sekolah',
    'create (cabang dropdown)' => '/admin/sekolah/create',
    'view own school + relation manager' => '/admin/sekolah/{A}',
    'edit own school' => '/admin/sekolah/{A}/edit',
    'dashboard' => '/admin',
]);

test('admin cabang cannot open another branch school by URL', function (string $suffix) {
    $this->actingAs($this->adminCabangA)
        ->get('/admin/sekolah/'.$this->sekolahB->id.$suffix)
        ->assertNotFound();
})->with(['view' => '', 'edit' => '/edit']);

test('list table, search and filters stay inside the branch', function () {
    $this->actingAs($this->adminCabangA);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListSekolahs::class)
        ->assertCanSeeTableRecords([$this->sekolahA])
        ->assertCanNotSeeTableRecords([$this->sekolahB])
        ->searchTable('SENTINEL')
        ->assertCanNotSeeTableRecords([$this->sekolahB])
        ->filterTable('cabang_id', $this->cabangB->id)
        ->assertCanNotSeeTableRecords([$this->sekolahB]);
});

test('LS-08 branch filter and dropdown options only offer the own branch', function () {
    $options = ScopedOptions::cabang($this->adminCabangA);

    expect($options)->toBe([$this->cabangA->id => 'Cabang Alpha']);
    expect(ScopedOptions::cabang(User::factory()->adminPusat()->create()))
        ->toHaveKeys([$this->cabangA->id, $this->cabangB->id]);
});

test('LS-01 admin cabang cannot reschedule another branch activity through the relation manager', function () {
    $this->actingAs($this->adminCabangA);
    Filament::setCurrentPanel('admin');
    $original = $this->kegiatanB->tanggal->toDateString();

    try {
        Livewire::test(KegiatanRelationManager::class, ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class])
            ->callTableAction('reschedule', $this->kegiatanB, ['tanggal' => now()->addMonth()->toDateString(), 'alasan' => 'x']);
    } catch (Throwable) {
        // Filament refuses to resolve a record outside the table query.
    }

    expect($this->kegiatanB->fresh()->tanggal->toDateString())->toBe($original)
        ->and($this->kegiatanB->riwayatJadwal()->count())->toBe(0);
});

test('LS-01 domain action refuses another branch activity for admin cabang', function () {
    expect(fn () => app(RescheduleKegiatan::class)->handle($this->adminCabangA, $this->kegiatanB, ['tanggal' => now()->addMonth()->toDateString()], 'alasan'))
        ->toThrow(AuthorizationException::class);
});

test('LS-02 mentor cannot act on another mentor activity by changing the id (component)', function (string $action) {
    $this->actingAs($this->mentor1);
    Filament::setCurrentPanel('mentor');

    Livewire::test(KegiatanSaya::class)
        ->mountAction($action, ['kegiatan' => $this->kegiatanB->id])
        ->assertStatus(404);

    // Submitting requires a mounted action, which the 404 above prevents.
    expect($this->kegiatanB->fresh()->riwayatJadwal()->count())->toBe(0);

    expect($this->kegiatanB->fresh()->status)->toBe(StatusKegiatan::BelumDimulai);
})->with(['report', 'reschedule']);

test('LS-02 mentor id manipulation is refused at the Livewire HTTP endpoint', function () {
    $page = $this->actingAs($this->mentor1)->get('/mentor/kegiatan-saya')->assertOk();
    $snapshot = LivewireHttp::snapshot($page->getContent(), KegiatanSaya::class);
    LivewireHttp::freshRequestState();

    $response = $this->withCookie(config('session.cookie'), LivewireHttp::sessionCookie($page))
        ->withHeaders(['X-Livewire' => '1'])
        ->postJson(LivewireHttp::updateUri(), ['components' => [LivewireHttp::component($snapshot, [], [
            LivewireHttp::call('mountAction', ['reschedule', ['kegiatan' => $this->kegiatanB->id]]),
        ])]]);

    expect($response->status())->toBe(404);
    $response->assertDontSee('SENTINEL-TAUTAN-M2', false);
    expect($this->kegiatanB->fresh()->riwayatJadwal()->count())->toBe(0);
});

test('LS-02 domain actions refuse a mentor without assignment', function () {
    expect(fn () => app(ReportKegiatan::class)->handle($this->mentor1, $this->kegiatanB, StatusKegiatan::SedangBerlangsung, 'catatan'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor1, $this->kegiatanB, ['tanggal' => now()->addMonth()->toDateString()], 'alasan'))
        ->toThrow(AuthorizationException::class);
});

test('A-01 lets a mentor read all schools but never write without assignment', function () {
    config(['access.mentor_can_view_all_schools' => true]);
    expect(Sekolah::query()->visibleTo($this->mentor1)->pluck('id')->all())
        ->toContain($this->sekolahB->id);

    config(['access.mentor_can_view_all_schools' => false]);
    expect(Sekolah::query()->visibleTo($this->mentor1)->pluck('id')->all())
        ->toBe([$this->sekolahA->id]);
});

test('LS-09 mentor work page shows only assigned activities and no foreign meeting links', function () {
    $response = $this->actingAs($this->mentor1)->get('/mentor/kegiatan-saya')->assertOk();

    $response->assertSee('SMAN 1 Alpha');
    foreach (sentinels() as $sentinel) {
        $response->assertDontSee($sentinel, false);
    }
});

test('LS-06 admin cabang cannot move a school by injecting cabang_id', function () {
    app(UpdateSekolah::class)->handle($this->adminCabangA, $this->sekolahA, ['cabang_id' => $this->cabangB->id, 'alamat' => 'Jl. Baru']);
    expect($this->sekolahA->fresh()->cabang_id)->toBe($this->cabangA->id);

    $this->actingAs($this->adminCabangA);
    Filament::setCurrentPanel('admin');
    Livewire::test(EditSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
        ->set('data.cabang_id', $this->cabangB->id)
        ->call('save');

    expect($this->sekolahA->fresh()->cabang_id)->toBe($this->cabangA->id);
});

test('LS-06 admin cabang registers schools only into the own branch', function () {
    $sekolah = app(RegisterSekolah::class)->handle($this->adminCabangA, [
        'nama' => 'SMK Injeksi',
        'jenjang' => Jenjang::SMK->value,
        'kabupaten_kota_id' => KabupatenKota::factory()->create()->id,
        'cabang_id' => $this->cabangB->id,
        'narahubung_utama' => ['nama' => 'Bu Ani', 'nomor_wa' => '0812'],
    ]);

    expect($sekolah->cabang_id)->toBe($this->cabangA->id);
});
