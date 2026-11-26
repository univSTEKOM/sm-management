<?php

/*
 * Fase 3 UI: school list and detail (PRD 13.2/13.3, UI brief 23 Nov 2026), header actions
 * per role, contacts relation manager, proposal queue and the mentor panel pages.
 */

use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Sekolah\Actions\ProposeNarahubungChange;
use App\Domain\Sekolah\Actions\RecordSekolahMou;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Admin\Resources\Sekolahs\Pages\ViewSekolah;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanAwalRelationManager;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanTlRelationManager;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\NarahubungRelationManager;
use App\Filament\Admin\Resources\UsulanNarahubungs\Pages\ListUsulanNarahubungs;
use App\Filament\Mentor\Resources\Sekolahs\Pages\ListSekolahs as MentorListSekolahs;
use App\Filament\Mentor\Resources\Sekolahs\Pages\ViewSekolah as MentorViewSekolah;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    $this->sekolahA = Fixtures::sekolah($this->cabangA, $this->mentor, ['nama' => 'SMA Negeri 1 Alpha']);
    $this->sekolahB = Fixtures::sekolah($this->cabangB, null, ['nama' => 'SMK Bravo']);
});

function adminPanel(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('admin');
}

describe('list (PRD 13.2, AC-SCH-04..08)', function () {
    test('AC-SCH-08 every row shows stage, mentor, contact, TL progress, both certificates, MOU and overall status', function () {
        Storage::fake('local');
        Storage::disk('local')->put('mou/mou.pdf', '%PDF-1.4');
        app(RecordSekolahMou::class)->handle($this->ap, $this->sekolahA, 'mou/mou.pdf', 'mou.pdf');
        Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::Awal->value, 'status' => StatusKegiatan::SedangBerlangsung->value]);
        adminPanel($this->ap);

        Livewire::test(ListSekolahs::class)
            ->assertCanSeeTableRecords([$this->sekolahA, $this->sekolahB])
            ->assertTableColumnStateSet('mou', 'Sudah MOU', $this->sekolahA)
            ->assertTableColumnStateSet('mou', 'Belum MOU', $this->sekolahB)
            ->assertTableColumnStateSet('status_pelatihan', 'Sedang berlangsung', $this->sekolahA)
            ->assertTableColumnStateSet('status_pelatihan', 'Belum dimulai', $this->sekolahB)
            ->assertTableColumnStateSet('sertifikat_awal', 'Belum Tersedia', $this->sekolahA)
            ->assertTableColumnStateSet('sertifikat_tl', 'Belum Tersedia', $this->sekolahA)
            ->assertTableColumnStateSet('penugasanAktif.mentor.name', $this->mentor->name, $this->sekolahA)
            ->assertTableColumnStateSet('narahubungUtama.nama', $this->sekolahA->narahubungUtama->nama, $this->sekolahA)
            ->assertTableColumnFormattedStateSet('posisi_tahap', 'Calon Mitra', $this->sekolahA);
    });

    test('AC-SCH-04 admin cabang only sees schools of their branch', function () {
        adminPanel($this->acA);

        Livewire::test(ListSekolahs::class)
            ->assertCanSeeTableRecords([$this->sekolahA])
            ->assertCanNotSeeTableRecords([$this->sekolahB]);
    });

    test('AC-SCH-07 filters combine with AND: MOU, overall status and mentor', function () {
        Storage::fake('local');
        Storage::disk('local')->put('mou/mou.pdf', '%PDF-1.4');
        app(RecordSekolahMou::class)->handle($this->ap, $this->sekolahA, 'mou/mou.pdf', 'mou.pdf');
        adminPanel($this->ap);

        Livewire::test(ListSekolahs::class)
            ->filterTable('mou', true)
            ->assertCanSeeTableRecords([$this->sekolahA])
            ->assertCanNotSeeTableRecords([$this->sekolahB])
            ->filterTable('mentor', $this->mentor->id)
            ->filterTable('status_pelatihan', 'belum_dimulai')
            ->assertCanSeeTableRecords([$this->sekolahA])
            ->filterTable('status_pelatihan', 'sedang_berlangsung')
            ->assertCanNotSeeTableRecords([$this->sekolahA, $this->sekolahB]);
    });

    test('AC-SCH-05 a school with categories X and Y appears under both category filters', function () {
        $x = KategoriPelatihan::create(['nama' => 'Kategori X']);
        $y = KategoriPelatihan::create(['nama' => 'Kategori Y']);
        $this->sekolahA->kategori()->sync([$x->id, $y->id]);
        adminPanel($this->ap);

        Livewire::test(ListSekolahs::class)
            ->filterTable('kategori', $x->id)->assertCanSeeTableRecords([$this->sekolahA])->assertCanNotSeeTableRecords([$this->sekolahB])
            ->filterTable('kategori', $y->id)->assertCanSeeTableRecords([$this->sekolahA])->assertCanNotSeeTableRecords([$this->sekolahB]);
    });

    test('AC-SCH-06 search by part of the name (timing measured on 5,000 rows, PHASE_LOG Fase 3)', function () {
        $s15 = Fixtures::sekolah($this->cabangA, null, ['nama' => 'SMP Negeri 15 Semarang']);
        adminPanel($this->ap);

        Livewire::test(ListSekolahs::class)
            ->searchTable('15')
            ->assertCanSeeTableRecords([$s15])
            ->assertCanNotSeeTableRecords([$this->sekolahA, $this->sekolahB]);
    });

    test('rendering the list does not issue queries per row (no N+1)', function () {
        adminPanel($this->ap);
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListSekolahs::class);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $base = $count();
        foreach (range(1, 8) as $i) {
            $s = Fixtures::sekolah($this->cabangA, $this->mentorLain);
            Fixtures::kegiatan($s, $this->mentorLain, ['status' => StatusKegiatan::Selesai->value]);
        }

        expect($count())->toBe($base);
    });
});

describe('detail (PRD 13.3) and header actions', function () {
    test('AC-SCH-17 detail page shows the tabs; another branch school is refused (404 via scoped query, plan 4.5)', function () {
        $this->actingAs($this->acA)->get("/admin/sekolah/{$this->sekolahA->id}")
            ->assertOk()->assertSee('Ringkasan')->assertSee('Penugasan Mentor')->assertSee('Progres &amp; Catatan', false)->assertSee('Riwayat')->assertSee('Sertifikat');

        $this->actingAs($this->acA)->get("/admin/sekolah/{$this->sekolahB->id}")->assertNotFound();
    });

    test('header actions follow the matrix: assign SA/AP, MOU SA/AP, correction SA only, archive in scope', function () {

        adminPanel($this->ap);
        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->assertActionVisible('assignMentor')->assertActionVisible('recordMou')
            ->assertActionHidden('correctCabang')->assertActionVisible('archive');

        adminPanel($this->sa);
        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->assertActionVisible('assignMentor')->assertActionVisible('recordMou')
            ->assertActionVisible('correctCabang');

        adminPanel($this->acA);
        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->assertActionHidden('assignMentor')->assertActionHidden('recordMou')
            ->assertActionHidden('correctCabang')->assertActionVisible('archive');
    });

    test('admin pusat assigns a mentor and uploads the MOU PDF from the detail page', function () {
        Storage::fake('local');
        adminPanel($this->ap);

        $file = UploadedFile::fake()->create('mou-bravo.pdf', 100, 'application/pdf');

        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahB->getRouteKey()])
            ->callAction('assignMentor', data: ['mentor_id' => $this->mentorLain->id])
            ->assertNotified('Mentor ditetapkan')
            ->callAction('recordMou', data: ['dokumen_file' => $file, 'tanggal' => now()->subDay()->toDateString()])
            ->assertNotified('MOU dicatat');

        $fresh = $this->sekolahB->fresh();
        expect($fresh->penugasanAktif->mentor_id)->toBe($this->mentorLain->id)
            ->and($fresh->isMou())->toBeTrue()
            ->and($fresh->mou_dokumen_nama_asli)->toBe('mou-bravo.pdf');
        Storage::disk('local')->assertExists($fresh->mou_dokumen_path);
    });

    test('superadmin uploads the MOU PDF from the detail page and only SA/AP can download it', function () {
        Storage::fake('local');
        adminPanel($this->sa);

        $file = UploadedFile::fake()->create('mou-alpha.pdf', 100, 'application/pdf');

        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->callAction('recordMou', data: ['dokumen_file' => $file, 'tanggal' => now()->subDay()->toDateString()])
            ->assertNotified('MOU dicatat');

        $fresh = $this->sekolahA->fresh();
        expect($fresh->isMou())->toBeTrue()
            ->and($fresh->mou_dokumen_nama_asli)->toBe('mou-alpha.pdf')
            ->and($fresh->mou_dicatat_oleh)->toBe($this->sa->id);

        $downloadUrl = route('mou.download', $fresh);

        $this->actingAs($this->sa)->get($downloadUrl)->assertOk();
        $this->actingAs($this->ap)->get($downloadUrl)->assertOk();
        $this->actingAs($this->acA)->get($downloadUrl)->assertForbidden();
        $this->actingAs($this->mentor)->get($downloadUrl)->assertForbidden();

        // Detail page displays the download link to SA/AP but hides it from Admin Cabang and Mentor
        $this->actingAs($this->sa)->get("/admin/sekolah/{$fresh->id}")->assertOk()->assertSee('mou-alpha.pdf');
        $this->actingAs($this->acA)->get("/admin/sekolah/{$fresh->id}")->assertOk()->assertSee('Sudah MOU')->assertDontSee($downloadUrl);
    });

    test('archive refused with open activities shows the domain message and keeps the school', function () {
        Fixtures::kegiatan($this->sekolahA, $this->mentor, ['status' => StatusKegiatan::BelumDimulai->value]);
        adminPanel($this->acA);

        Livewire::test(ViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->callAction('archive', data: ['alasan' => 'Mundur'])
            ->assertNotified();

        expect($this->sekolahA->fresh()->isArchived())->toBeFalse();
    });
});

describe('activities and certificates on the detail page (AC-SCH-09/10)', function () {
    test('AC-SCH-09 initial and follow-up activities are in separate tabs and never in both', function () {
        $awal = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::Awal->value]);
        $tl = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::TindakLanjut->value]);
        adminPanel($this->ap);
        $args = ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class];

        Livewire::test(KegiatanAwalRelationManager::class, $args)
            ->assertCanSeeTableRecords([$awal])->assertCanNotSeeTableRecords([$tl]);
        Livewire::test(KegiatanTlRelationManager::class, $args)
            ->assertCanSeeTableRecords([$tl])->assertCanNotSeeTableRecords([$awal]);
    });

    test('AC-SCH-10 an available certificate link opens in a new tab for entitled users only', function () {
        $awal = Fixtures::kegiatan($this->sekolahA, $this->mentor, ['tahap' => Tahap::Awal->value, 'status' => StatusKegiatan::Selesai->value, 'tanggal' => now()->subDay()->toDateString()]);
        Sertifikat::create(['sekolah_id' => $this->sekolahA->id, 'tahap' => Tahap::Awal->value, 'kegiatan_acuan_id' => $awal->id, 'link' => 'https://drive.example.test/sertifikat-alpha.pdf']);

        $this->actingAs($this->acA)->get("/admin/sekolah/{$this->sekolahA->id}?tab=sertifikat::data::tab")
            ->assertOk()
            ->assertSee('href="https://drive.example.test/sertifikat-alpha.pdf"', false)
            ->assertSee('target="_blank"', false);

        // A mentor who may read every school (A-01) but is not assigned sees the status only.
        $this->actingAs($this->mentorLain)->get("/mentor/sekolah/{$this->sekolahA->id}")
            ->assertOk()->assertSee('Tersedia')->assertDontSee('sertifikat-alpha.pdf');
    });
});

describe('contacts relation manager (AC-SCH-12)', function () {
    test('admin cabang adds a primary contact and the previous primary is demoted', function () {
        adminPanel($this->acA);

        Livewire::test(NarahubungRelationManager::class, ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class])
            ->callTableAction('addNarahubung', data: ['nama' => 'Bu Rina', 'jabatan' => 'Kepala TU', 'nomor_wa' => '0813 4444 5555', 'utama' => true])
            ->assertNotified('Narahubung ditambahkan');

        expect(Narahubung::where('sekolah_id', $this->sekolahA->id)->where('is_utama', true)->sole()->nama)->toBe('Bu Rina');
    });

    test('the primary contact has no deactivate action', function () {
        adminPanel($this->ap);
        $utama = $this->sekolahA->narahubungUtama;

        Livewire::test(NarahubungRelationManager::class, ['ownerRecord' => $this->sekolahA, 'pageClass' => ViewSekolah::class])
            ->assertTableActionHidden('deactivateNarahubung', $utama)
            ->assertTableActionHidden('setUtama', $utama)
            ->assertTableActionVisible('editNarahubung', $utama);
    });
});

describe('proposal queue (AC-SCH-22/23)', function () {
    beforeEach(function () {
        $this->mentorB = User::factory()->mentor()->create();
        Fixtures::assign($this->sekolahB, $this->mentorB);
        $this->usulanA = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolahA, ['nomor_wa' => '0811 9999 0000'], $this->sekolahA->narahubungUtama);
        $this->usulanB = app(ProposeNarahubungChange::class)->handle($this->mentorB, $this->sekolahB, ['nama' => 'Pak Dedi', 'nomor_wa' => '0812 3333 4444']);
    });

    test('AC-SCH-23 admin cabang only sees proposals of their branch; superadmin has no access', function () {
        adminPanel($this->acA);
        Livewire::test(ListUsulanNarahubungs::class)
            ->assertCanSeeTableRecords([$this->usulanA])
            ->assertCanNotSeeTableRecords([$this->usulanB]);

        $this->actingAs($this->sa)->get('/admin/usulan-narahubung')->assertForbidden();
    });

    test('AC-SCH-22 admin pusat approves from the queue and the contact changes', function () {
        adminPanel($this->ap);

        Livewire::test(ListUsulanNarahubungs::class)
            ->assertSee('WhatsApp: '.$this->sekolahA->narahubungUtama->nomor_wa.' → 0811 9999 0000')
            ->callTableAction('approve', $this->usulanA)
            ->assertNotified('Usulan disetujui')
            ->callTableAction('reject', $this->usulanB, data: ['alasan_penolakan' => 'Data belum lengkap'])
            ->assertNotified('Usulan ditolak');

        expect($this->sekolahA->narahubungUtama->fresh()->nomor_wa)->toBe('0811 9999 0000')
            ->and($this->usulanB->fresh()->status)->toBe(NarahubungUsulan::DITOLAK);
    });
});

describe('mentor panel', function () {
    test('mentor sees assigned schools by default and every school after clearing the filter (A-01)', function () {
        $this->actingAs($this->mentor);
        Filament::setCurrentPanel('mentor');

        Livewire::test(MentorListSekolahs::class)
            ->assertCanSeeTableRecords([$this->sekolahA])
            ->assertCanNotSeeTableRecords([$this->sekolahB])
            ->filterTable('saya', false)
            ->assertCanSeeTableRecords([$this->sekolahA, $this->sekolahB]);
    });

    test('unassigned school: no report notes tab and no proposal action; assigned school: proposal works', function () {
        $this->actingAs($this->mentor)->get("/mentor/sekolah/{$this->sekolahB->id}")
            ->assertOk()->assertDontSee('Progres &amp; Catatan', false);

        Filament::setCurrentPanel('mentor');
        Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahB->getRouteKey()])
            ->assertActionHidden('proposeNarahubung');

        Livewire::test(MentorViewSekolah::class, ['record' => $this->sekolahA->getRouteKey()])
            ->assertActionVisible('proposeNarahubung')
            ->callAction('proposeNarahubung', data: ['narahubung_id' => null, 'nama' => 'Bu Wati', 'nomor_wa' => '0857 1212 3434'])
            ->assertNotified('Usulan dikirim');

        expect(NarahubungUsulan::where('diusulkan_oleh', $this->mentor->id)->sole()->data_usulan['nama'])->toBe('Bu Wati')
            ->and(Narahubung::where('nama', 'Bu Wati')->exists())->toBeFalse();
    });

    test('admin users cannot open the mentor school page', function () {
        $this->actingAs($this->ap)->get('/mentor/sekolah')->assertForbidden();
    });
});
