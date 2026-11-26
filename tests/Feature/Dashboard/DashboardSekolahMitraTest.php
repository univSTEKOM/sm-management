<?php

/*
 * Dashboard Sekolah Mitra: order of sections, one-row-per-school table, scope, search,
 * combinable filters. Presentation only; scope is the same visibleTo() the resource uses.
 */

use App\Domain\Master\Models\Cabang;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Jenjang;
use App\Filament\Pages\DashboardSekolahMitra;
use App\Filament\Widgets\KlasemenKategoriChart;
use App\Filament\Widgets\SekolahMitraStats;
use App\Filament\Widgets\SekolahMitraTable;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->mentor = User::factory()->mentor()->create();

    $this->sdA = Fixtures::sekolah($this->cabangA, null, ['nama' => 'SMAN Alpha Satu', 'jenjang' => Jenjang::SMA]);
    $this->smaA = Fixtures::sekolah($this->cabangA, null, ['nama' => 'SMKN Beta Dua', 'jenjang' => Jenjang::SMK]);
    $this->sdB = Fixtures::sekolah($this->cabangB, null, ['nama' => 'SMAN Gamma Tiga', 'jenjang' => Jenjang::SMA]);

    $this->tampil = function (User $user, string $panel = 'admin') {
        $this->actingAs($user);
        Filament::setCurrentPanel($panel);
    };
});

test('sections are ordered: statistics, klasemen, then the school table', function () {
    ($this->tampil)($this->ap);

    $widgets = Livewire::test(DashboardSekolahMitra::class)->instance()->getWidgets();

    expect($widgets)->toBe([SekolahMitraStats::class, KlasemenKategoriChart::class, SekolahMitraTable::class])
        ->and(Livewire::test(DashboardSekolahMitra::class)->instance()->getTitle())->toBe('Dashboard Sekolah Mitra');
});

test('both panels use the dashboard page', function () {
    foreach (['admin', 'mentor'] as $panel) {
        expect(Filament::getPanel($panel)->getPages())->toContain(DashboardSekolahMitra::class);
    }
});

test('table lists one school per row with all required columns', function () {
    ($this->tampil)($this->ap);

    $t = Livewire::test(SekolahMitraTable::class)
        ->assertCanSeeTableRecords([$this->sdA, $this->smaA, $this->sdB]);

    foreach (['nama', 'jenjang', 'kabupatenKota.nama', 'status_program', 'penugasanAktif.mentor.name', 'narahubungUtama.nama', 'sertifikat', 'mou', 'progres_tl', 'update_mentor_at'] as $kolom) {
        $t->assertTableColumnExists($kolom);
    }
});

test('table paginates 10 per page by default', function () {
    ($this->tampil)($this->ap);
    foreach (range(1, 12) as $i) {
        Fixtures::sekolah($this->cabangA, null, ['nama' => sprintf('SMAN Massal %02d', $i)]);
    }

    $t = Livewire::test(SekolahMitraTable::class)->assertCountTableRecords(15);

    expect($t->instance()->getTableRecords())->toHaveCount(10);
});

test('search finds a school by name', function () {
    ($this->tampil)($this->ap);

    Livewire::test(SekolahMitraTable::class)
        ->searchTable('Beta')
        ->assertCanSeeTableRecords([$this->smaA])
        ->assertCanNotSeeTableRecords([$this->sdA, $this->sdB]);
});

test('filters combine with AND', function () {
    ($this->tampil)($this->ap);

    Livewire::test(SekolahMitraTable::class)
        ->filterTable('jenjang', 'SMA')
        ->assertCanSeeTableRecords([$this->sdA, $this->sdB])
        ->assertCanNotSeeTableRecords([$this->smaA])
        ->searchTable('Gamma')
        ->assertCanSeeTableRecords([$this->sdB])
        ->assertCanNotSeeTableRecords([$this->sdA]);
});

test('Admin Cabang only gets rows of its own branch', function () {
    ($this->tampil)($this->acA);

    Livewire::test(SekolahMitraTable::class)
        ->assertCanSeeTableRecords([$this->sdA, $this->smaA])
        ->assertCanNotSeeTableRecords([$this->sdB]);
});

test('archived schools are hidden', function () {
    ($this->tampil)($this->ap);
    $this->sdA->forceFill(['diarsipkan_at' => now(), 'alasan_arsip' => 'Tutup'])->save();

    Livewire::test(SekolahMitraTable::class)->assertCanNotSeeTableRecords([$this->sdA])->assertCanSeeTableRecords([$this->smaA]);
});

test('mentor sees the table and each row links to the mentor panel detail', function () {
    ($this->tampil)($this->mentor, 'mentor');

    $t = Livewire::test(SekolahMitraTable::class)->assertCanSeeTableRecords([$this->sdA]);

    expect($t->instance()->getTable()->getRecordUrl($this->sdA))->toContain('/mentor/sekolah/');
});

test('stats count only schools in scope', function () {
    ($this->tampil)($this->acA);
    $this->smaA->forceFill(['tanggal_mitra' => now()->toDateString()])->save();

    $stats = collect((fn () => $this->getStats())->call(Livewire::test(SekolahMitraStats::class)->instance()))
        ->mapWithKeys(fn ($s) => [(string) $s->getLabel() => $s->getValue()]);

    expect($stats['Sekolah aktif'])->toBe(2)->and($stats['Mitra resmi'])->toBe(1);
});
