<?php

/*
 * Fase 4 UI: the cross-school schedule list is read-only and scoped (AC-JDA-06, AC-AUTH-04 style
 * scoping), school-page actions per role, and the mentor panel action for follow-up sessions.
 */

use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Jadwals\Pages\ListJadwals;
use App\Filament\Admin\Resources\Sekolahs\Pages\ViewSekolah;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanAwalRelationManager;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanTlRelationManager;
use App\Filament\Mentor\Resources\Sekolahs\Pages\ViewSekolah as MentorViewSekolah;
use App\Filament\Support\JadwalActions;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->sa = User::factory()->superadmin()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->acB = User::factory()->adminCabang($this->cabangB)->create();
    $this->mentor = User::factory()->mentor()->create();
    $this->mentorLain = User::factory()->mentor()->create();
    $this->sekolahA = Fixtures::sekolah($this->cabangA, $this->mentor, ['posisi_tahap' => PosisiTahap::MitraBelumTl]);
    $this->sekolahB = Fixtures::sekolah($this->cabangB, $this->mentorLain);

    $this->jenis = JenisKegiatan::create(['nama' => 'Pelatihan']);
    $this->online = MetodeKegiatan::create(['nama' => 'Online', 'butuh_tautan' => true]);
    $this->offline = MetodeKegiatan::create(['nama' => 'Offline', 'butuh_lokasi' => true]);
    AppSetting::create(['key' => AppSetting::LOKASI_KAMPUS_PUSAT, 'value' => 'Kampus Pusat, Semarang']);

    $this->awalA = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::Awal, 'urutan' => 1, 'nama_sesi' => 'Pelatihan Awal', 'status' => StatusKegiatan::Selesai, 'tanggal' => now()->subWeek()->toDateString()]);
    $this->tlA = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::TindakLanjut, 'urutan' => 1, 'nama_sesi' => 'Sesi 1']);
    $this->awalB = Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tahap' => Tahap::Awal, 'urutan' => 1, 'nama_sesi' => 'Pelatihan Awal']);
    $this->tlB = Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tahap' => Tahap::TindakLanjut, 'urutan' => 1, 'nama_sesi' => 'Sesi 1']);

    $this->panel = function (User $user, string $panel = 'admin') {
        test()->actingAs($user);
        Filament::setCurrentPanel($panel);
    };
    $this->rmArgs = fn () => ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class];
});

describe('cross-school schedule list', function () {
    test('Admin Pusat and Superadmin see every school, split into the two stage tabs', function () {
        foreach ([$this->ap, $this->sa] as $user) {
            ($this->panel)($user);

            Livewire::test(ListJadwals::class)->set('activeTab', 'awal')
                ->assertCanSeeTableRecords([$this->awalA, $this->awalB])
                ->assertCanNotSeeTableRecords([$this->tlA, $this->tlB]);
            Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut')
                ->assertCanSeeTableRecords([$this->tlA, $this->tlB])
                ->assertCanNotSeeTableRecords([$this->awalA, $this->awalB]);
        }
    });

    test('AC-JDA-06 Admin Cabang sees only the schedule of its own branch', function () {
        ($this->panel)($this->acA);

        Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut')
            ->assertCanSeeTableRecords([$this->tlA])->assertCanNotSeeTableRecords([$this->tlB]);
        Livewire::test(ListJadwals::class)->set('activeTab', 'awal')
            ->assertCanSeeTableRecords([$this->awalA])->assertCanNotSeeTableRecords([$this->awalB]);
    });

    test('the list is read-only for every role: no create page and no row or bulk actions', function () {
        foreach ([$this->sa, $this->ap, $this->acA] as $user) {
            ($this->panel)($user);

            $this->actingAs($user)->get('/admin/jadwal')->assertOk();
            $this->actingAs($user)->get('/admin/jadwal/create')->assertNotFound();
            Livewire::test(ListJadwals::class)->assertTableActionDoesNotExist('reschedule')->assertTableBulkActionDoesNotExist('delete');
        }
    });

    test('filters narrow by status, mentor and date range', function () {
        $selesai = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['status' => StatusKegiatan::Selesai, 'tanggal' => '2026-01-10']);
        $nanti = Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tanggal' => '2026-09-10']);
        ($this->panel)($this->ap);

        Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut')
            ->filterTable('status', StatusKegiatan::Selesai->value)
            ->assertCanSeeTableRecords([$selesai])->assertCanNotSeeTableRecords([$this->tlA, $nanti]);

        Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut')
            ->filterTable('mentor_pelaksana_id', $this->mentorLain->id)
            ->assertCanSeeTableRecords([$nanti, $this->tlB])->assertCanNotSeeTableRecords([$selesai, $this->tlA]);

        Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut')
            ->filterTable('rentang', ['dari' => '2026-01-01', 'sampai' => '2026-03-01'])
            ->assertCanSeeTableRecords([$selesai])->assertCanNotSeeTableRecords([$nanti]);
    });

    test('a mentor cannot open the admin schedule page', function () {
        $this->actingAs($this->mentor)->get('/admin/jadwal')->assertForbidden();
    });

    test('the list does not issue a query per row', function () {
        foreach (range(1, 15) as $i) {
            Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::TindakLanjut]);
        }
        ($this->panel)($this->ap);

        $queries = fn () => (function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListJadwals::class)->set('activeTab', 'tindak_lanjut');

            return count(DB::getQueryLog());
        })();

        $with17 = $queries();
        foreach (range(1, 15) as $i) {
            Fixtures::kegiatan($this->sekolahB, $this->mentorLain, ['tahap' => Tahap::TindakLanjut]);
        }

        expect($queries())->toBeLessThanOrEqual($with17 + 2);
    });
});

describe('school page actions', function () {
    test('Admin Cabang schedules the initial training: fixed method and location, hidden once one exists', function () {
        $sekolah = Fixtures::sekolah($this->cabangA, $this->mentor);
        ($this->panel)($this->acA);
        $args = ['ownerRecord' => $sekolah, 'pageClass' => ViewSekolah::class];

        Livewire::test(KegiatanAwalRelationManager::class, $args)
            ->callTableAction('tambahAwal', data: [
                'tanggal' => now()->addWeek()->toDateString(), 'waktu_mulai' => '09:00', 'waktu_selesai' => '12:00',
                'jenis_kegiatan_id' => $this->jenis->id, 'detail_lokasi' => 'Aula',
            ])
            ->assertNotified('Jadwal pelatihan awal dibuat');

        $awal = $sekolah->kegiatan()->sole();
        expect($awal->metode_id)->toBe($this->offline->id)->and($awal->lokasi)->toBe('Kampus Pusat, Semarang — Aula');

        Livewire::test(KegiatanAwalRelationManager::class, $args)->assertTableActionHidden('tambahAwal');
    });

    test('AC-JDA-02 the initial training action is not offered once one exists (the domain refuses a forced call too)', function () {
        ($this->panel)($this->ap);

        Livewire::test(KegiatanAwalRelationManager::class, ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class])
            ->assertTableActionHidden('tambahAwal');
    });

    test('the initial training action is not offered to Admin Cabang of another branch', function () {
        ($this->panel)($this->acB);

        // The branch B admin cannot even reach school A (404 from the scoped query).
        $this->actingAs($this->acB)->get("/admin/sekolah/{$this->sekolahA->id}")->assertNotFound();
    });

    test('AC-JTL-02 an admin adds an online session; the next number is 2 and the composition shows', function () {
        ($this->panel)($this->ap);
        $args = ($this->rmArgs)();

        Livewire::test(KegiatanTlRelationManager::class, $args)
            ->callTableAction('tambahSesi', data: [
                'tanggal' => now()->addWeeks(2)->toDateString(), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00',
                'jenis_kegiatan_id' => $this->jenis->id, 'metode_id' => $this->online->id, 'tautan_pertemuan' => 'https://meet.example.test/x',
            ])
            ->assertNotified('Sesi ditambahkan')
            ->assertSee('Online 2 · Offline 0 (acuan juklak 4+1)');

        expect($this->sekolahA->kegiatan()->where('urutan', 2)->where('tahap', 'tindak_lanjut')->value('nama_sesi'))->toBe('Sesi 2');
    });

    test('AC-JTL-09 offline without a location is refused in the form', function () {
        ($this->panel)($this->ap);

        Livewire::test(KegiatanTlRelationManager::class, ($this->rmArgs)())
            ->callTableAction('tambahSesi', data: [
                'tanggal' => now()->addWeeks(2)->toDateString(), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00',
                'jenis_kegiatan_id' => $this->jenis->id, 'metode_id' => $this->offline->id,
            ])
            ->assertHasTableActionErrors(['lokasi' => 'required']);
    });

    test('executor and detail actions follow the matrix: Admin Pusat both, Admin Cabang detail only', function () {
        ($this->panel)($this->ap);
        Livewire::test(KegiatanTlRelationManager::class, ($this->rmArgs)())
            ->assertTableActionVisible('tunjukPelaksana', $this->tlA)->assertTableActionVisible('ubahDetail', $this->tlA)
            ->assertTableActionVisible('reschedule', $this->tlA)->assertTableActionVisible('riwayatJadwal', $this->tlA);

        ($this->panel)($this->acA);
        Livewire::test(KegiatanTlRelationManager::class, ($this->rmArgs)())
            ->assertTableActionHidden('tunjukPelaksana', $this->tlA)->assertTableActionVisible('ubahDetail', $this->tlA)
            ->assertTableActionVisible('reschedule', $this->tlA);
    });

    test('AC-JTL-05 renaming a session from the table keeps its number', function () {
        ($this->panel)($this->acA);

        Livewire::test(KegiatanTlRelationManager::class, ($this->rmArgs)())
            ->callTableAction('ubahDetail', $this->tlA, data: ['nama_sesi' => 'Workshop Offline', 'jenis_kegiatan_id' => $this->jenis->id, 'keterangan' => 'x', 'version' => $this->tlA->version])
            ->assertNotified('Detail diperbarui');

        expect($this->tlA->fresh()->nama_sesi)->toBe('Workshop Offline')->and($this->tlA->fresh()->urutan)->toBe(1);
    });

    test('Admin Pusat designates an executor from the table and the schedule history lists versions', function () {
        ($this->panel)($this->ap);
        $table = Livewire::test(KegiatanTlRelationManager::class, ($this->rmArgs)());

        $table->callTableAction('tunjukPelaksana', $this->tlA, data: ['mentor_id' => $this->mentorLain->id, 'version' => $this->tlA->version])
            ->assertNotified('Pelaksana ditunjuk');
        expect($this->tlA->fresh()->mentor_pelaksana_id)->toBe($this->mentorLain->id);

        $table->callTableAction('reschedule', $this->tlA->fresh(), data: ['tanggal' => now()->addMonth()->toDateString(), 'waktu_mulai' => '10:00', 'waktu_selesai' => '11:00', 'tautan_pertemuan' => 'https://meet.example.test/baru', 'alasan' => 'Libur sekolah', 'version' => $this->tlA->fresh()->version])
            ->assertNotified('Jadwal diperbarui');
        $table->assertTableActionVisible('riwayatJadwal', $this->tlA);
        $html = JadwalActions::riwayatHtml($this->tlA->fresh());
        expect($html)->toContain('Versi 1')->toContain('Libur sekolah')->toContain($this->ap->name)
            ->and(JadwalActions::riwayatHtml($this->tlB))->toContain('belum pernah diubah');
    });
});

describe('mentor panel', function () {
    test('AC-JTL-10 the responsible mentor adds a session for the school; another mentor cannot', function () {
        ($this->panel)($this->mentor, 'mentor');

        Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->assertActionVisible('tambahSesi')
            ->callAction('tambahSesi', data: [
                'tanggal' => now()->addWeeks(2)->toDateString(), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00',
                'jenis_kegiatan_id' => $this->jenis->id, 'metode_id' => $this->online->id, 'tautan_pertemuan' => 'https://meet.example.test/m',
            ])
            ->assertNotified('Sesi ditambahkan');

        expect($this->sekolahA->kegiatan()->where('tahap', 'tindak_lanjut')->count())->toBe(2)
            ->and($this->ap->notifications()->count())->toBe(1);

        ($this->panel)($this->mentorLain, 'mentor');
        Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])->assertActionHidden('tambahSesi');
    });

    test('AC-JTL-13 the add button disappears for the mentor once Admin Pusat has set the last session', function () {
        $this->tlA->forceFill(['is_sesi_terakhir' => true])->save();
        ($this->panel)($this->mentor, 'mentor');

        $page = Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()]);
        // The domain refuses the request even when the button is forced (403).
        $page->callAction('tambahSesi', data: [
            'tanggal' => now()->addWeeks(2)->toDateString(), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00',
            'jenis_kegiatan_id' => $this->jenis->id, 'metode_id' => $this->online->id, 'tautan_pertemuan' => 'https://meet.example.test/m',
        ]);

        expect($this->sekolahA->kegiatan()->where('tahap', 'tindak_lanjut')->count())->toBe(1);
    });

    test('a mentor never gets the initial training action or executor action', function () {
        ($this->panel)($this->mentor, 'mentor');

        Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])->assertActionDoesNotExist('tambahAwal');
    });
});
