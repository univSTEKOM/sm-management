<?php

/*
 * Fase 3 domain actions: archive, branch correction, contacts and proposals, mentor
 * assignment, MOU, and the derived overall status (AC-SCH-11..23, BR-SCH-05/06/11/12,
 * BR-MNT-03/05, owner decisions D-02/D-03).
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Master\Models\Cabang;
use App\Domain\Mentor\Actions\AssignMentor;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Actions\ArchiveSekolah;
use App\Domain\Sekolah\Actions\CorrectSekolahCabang;
use App\Domain\Sekolah\Actions\DecideNarahubungProposal;
use App\Domain\Sekolah\Actions\ManageNarahubung;
use App\Domain\Sekolah\Actions\ProposeNarahubungChange;
use App\Domain\Sekolah\Actions\RecordSekolahMou;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungRiwayat;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Models\SekolahCabangKoreksi;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\StatusSertifikat;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
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
    $this->sekolah = Fixtures::sekolah($this->cabangA, $this->mentor);
});

describe('archive (BR-SCH-05, AC-SCH-11/15, K-09)', function () {
    test('AC-SCH-15 archive requires a reason and keeps the record', function () {
        expect(fn () => app(ArchiveSekolah::class)->handle($this->acA, $this->sekolah, '  '))->toThrow(ValidationException::class);

        app(ArchiveSekolah::class)->handle($this->acA, $this->sekolah, 'Sekolah mengundurkan diri');

        $fresh = $this->sekolah->fresh();
        expect($fresh->isArchived())->toBeTrue()
            ->and($fresh->alasan_arsip)->toBe('Sekolah mengundurkan diri')
            ->and(AuditLog::where('aksi', 'sekolah.archive')->count())->toBe(1);
    });

    test('K-09 archive is refused while activities are open', function () {
        Fixtures::kegiatan($this->sekolah, $this->mentor, ['status' => StatusKegiatan::BelumDimulai->value]);

        expect(fn () => app(ArchiveSekolah::class)->handle($this->ap, $this->sekolah, 'Mundur'))->toThrow(ValidationException::class);
        expect($this->sekolah->fresh()->isArchived())->toBeFalse();
    });

    test('admin cabang of another branch and mentors cannot archive', function () {
        expect(fn () => app(ArchiveSekolah::class)->handle($this->acB, $this->sekolah, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ArchiveSekolah::class)->handle($this->mentor, $this->sekolah, 'x'))->toThrow(AuthorizationException::class);
    });

    test('AC-SCH-11 schools are never deleted through the policy', function () {
        foreach ([$this->sa, $this->ap, $this->acA] as $user) {
            expect($user->can('delete', $this->sekolah))->toBeFalse();
        }
    });

    test('an archived school can no longer be edited', function () {
        app(ArchiveSekolah::class)->handle($this->ap, $this->sekolah, 'Mundur');

        expect($this->ap->can('update', $this->sekolah->fresh()))->toBeFalse()
            ->and($this->ap->can('manageNarahubung', $this->sekolah->fresh()))->toBeFalse();
    });
});

describe('branch correction (BR-SCH-12, AC-SCH-18/19)', function () {
    test('AC-SCH-18 superadmin corrects the branch of a school without activity, with reason and audit', function () {
        expect(fn () => app(CorrectSekolahCabang::class)->handle($this->sa, $this->sekolah, $this->cabangB->id, ''))->toThrow(ValidationException::class);

        app(CorrectSekolahCabang::class)->handle($this->sa, $this->sekolah, $this->cabangB->id, 'Salah input cabang');

        expect($this->sekolah->fresh()->cabang_id)->toBe($this->cabangB->id);
        $koreksi = SekolahCabangKoreksi::sole();
        expect($koreksi->cabang_lama_id)->toBe($this->cabangA->id)->and($koreksi->alasan)->toBe('Salah input cabang');
        $audit = AuditLog::where('aksi', 'sekolah.correct_cabang')->sole();
        expect($audit->sebelum)->toBe(['cabang_id' => $this->cabangA->id])
            ->and($audit->sesudah)->toEqual(['cabang_id' => $this->cabangB->id, 'alasan' => 'Salah input cabang']);
    });

    test('AC-SCH-19 correction is refused once any activity exists, even a cancelled one', function () {
        Fixtures::kegiatan($this->sekolah, $this->mentor, ['status' => StatusKegiatan::Dibatalkan->value]);

        expect(fn () => app(CorrectSekolahCabang::class)->handle($this->sa, $this->sekolah, $this->cabangB->id, 'Salah'))
            ->toThrow(AuthorizationException::class);
        expect($this->sekolah->fresh()->cabang_id)->toBe($this->cabangA->id);
    });

    test('AC-SCH-13 only superadmin may correct the branch', function () {
        foreach ([$this->ap, $this->acA, $this->mentor] as $user) {
            expect(fn () => app(CorrectSekolahCabang::class)->handle($user, $this->sekolah, $this->cabangB->id, 'x'))->toThrow(AuthorizationException::class);
        }
    });
});

describe('contacts (BR-SCH-06, AC-SCH-12/16)', function () {
    test('AC-SCH-12 a contact needs a name and a WhatsApp number', function () {
        expect(fn () => app(ManageNarahubung::class)->add($this->acA, $this->sekolah, ['nama' => '', 'nomor_wa' => '0812345678']))->toThrow(ValidationException::class)
            ->and(fn () => app(ManageNarahubung::class)->add($this->acA, $this->sekolah, ['nama' => 'Bu Sri', 'nomor_wa' => '']))->toThrow(ValidationException::class)
            ->and(fn () => app(ManageNarahubung::class)->add($this->acA, $this->sekolah, ['nama' => 'Bu Sri', 'nomor_wa' => 'abc']))->toThrow(ValidationException::class);
    });

    test('AC-SCH-12 exactly one active primary contact: switching moves the flag and the primary cannot be deactivated', function () {
        $utamaLama = $this->sekolah->narahubungUtama;
        $baru = app(ManageNarahubung::class)->add($this->acA, $this->sekolah, ['nama' => 'Pak Budi', 'nomor_wa' => '0813 1111 2222', 'jabatan' => 'Wakasek']);

        expect(fn () => app(ManageNarahubung::class)->deactivate($this->acA, $utamaLama))->toThrow(ValidationException::class);

        app(ManageNarahubung::class)->setUtama($this->acA, $baru);

        expect(Narahubung::where('sekolah_id', $this->sekolah->id)->where('is_utama', true)->where('aktif', true)->pluck('id')->all())->toBe([$baru->id]);

        app(ManageNarahubung::class)->deactivate($this->acA, $utamaLama->fresh());
        expect($utamaLama->fresh()->aktif)->toBeFalse();
    });

    test('AC-SCH-16 an update keeps the previous version in the history', function () {
        $utama = $this->sekolah->narahubungUtama;
        $namaLama = $utama->nama;

        app(ManageNarahubung::class)->update($this->ap, $utama, ['nomor_wa' => '0899 0000 1111']);

        $riwayat = NarahubungRiwayat::where('narahubung_id', $utama->id)->latest('id')->first();
        expect($riwayat->data_sebelum['nama'])->toBe($namaLama)
            ->and($riwayat->data_sesudah['nomor_wa'])->toBe('0899 0000 1111')
            ->and($riwayat->diubah_oleh)->toBe($this->ap->id);
        expect(fn () => $riwayat->delete())->toThrow(LogicException::class);
    });

    test('admin cabang of another branch and mentors cannot manage contacts', function () {
        expect(fn () => app(ManageNarahubung::class)->add($this->acB, $this->sekolah, ['nama' => 'X', 'nomor_wa' => '08123456']))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ManageNarahubung::class)->add($this->mentor, $this->sekolah, ['nama' => 'X', 'nomor_wa' => '08123456']))->toThrow(AuthorizationException::class);
    });
});

describe('contact proposals (BR-SCH-11, AC-SCH-21/22/23)', function () {
    test('AC-SCH-21 a proposal changes nothing yet and notifies Admin Pusat and the branch admin', function () {
        $utama = $this->sekolah->narahubungUtama;

        $usulan = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolah, ['nomor_wa' => '0811 2222 3333'], $utama);

        expect($usulan->status)->toBe(NarahubungUsulan::DIAJUKAN)
            ->and($utama->fresh()->nomor_wa)->not->toBe('0811 2222 3333');

        expect($this->ap->notifications()->count())->toBe(1)
            ->and($this->acA->notifications()->count())->toBe(1)
            ->and($this->acB->notifications()->count())->toBe(0)
            ->and($this->mentor->notifications()->count())->toBe(0);
    });

    test('a mentor without assignment cannot propose', function () {
        expect(fn () => app(ProposeNarahubungChange::class)->handle($this->mentorLain, $this->sekolah, ['nama' => 'X', 'nomor_wa' => '08123456']))
            ->toThrow(AuthorizationException::class);
    });

    test('AC-SCH-22 approval applies the change and the history links the proposal', function () {
        $utama = $this->sekolah->narahubungUtama;
        $usulan = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolah, ['nomor_wa' => '0811 2222 3333'], $utama);

        app(DecideNarahubungProposal::class)->handle($this->acA, $usulan, true);

        $usulan->refresh();
        expect($utama->fresh()->nomor_wa)->toBe('0811 2222 3333')
            ->and($usulan->status)->toBe(NarahubungUsulan::DISETUJUI)
            ->and($usulan->diputuskan_oleh)->toBe($this->acA->id)
            ->and($usulan->diputuskan_at)->not->toBeNull()
            ->and($usulan->diajukan_at)->not->toBeNull()
            ->and($usulan->data_sebelum['nomor_wa'])->toBe($utama->getOriginal('nomor_wa'));

        $riwayat = NarahubungRiwayat::where('usulan_id', $usulan->id)->sole();
        expect($riwayat->diubah_oleh)->toBe($this->acA->id);
        expect($this->mentor->notifications()->count())->toBe(1);
    });

    test('approval of a proposal for a new contact adds a non-primary contact', function () {
        $usulan = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolah, ['nama' => 'Bu Ani', 'nomor_wa' => '0812 7777 8888', 'jabatan' => 'Guru BK']);

        app(DecideNarahubungProposal::class)->handle($this->ap, $usulan, true);

        $baru = Narahubung::where('nama', 'Bu Ani')->sole();
        expect($baru->is_utama)->toBeFalse()->and($baru->jabatan)->toBe('Guru BK');
    });

    test('AC-SCH-22 rejection needs a reason, changes no data and is recorded', function () {
        $utama = $this->sekolah->narahubungUtama;
        $usulan = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolah, ['nomor_wa' => '0811 2222 3333'], $utama);

        expect(fn () => app(DecideNarahubungProposal::class)->handle($this->ap, $usulan, false, ''))->toThrow(ValidationException::class);

        app(DecideNarahubungProposal::class)->handle($this->ap, $usulan, false, 'Nomor tidak aktif');

        expect($usulan->fresh())->status->toBe(NarahubungUsulan::DITOLAK)->alasan_penolakan->toBe('Nomor tidak aktif')
            ->and($utama->fresh()->nomor_wa)->toBe($utama->nomor_wa)
            ->and(AuditLog::where('aksi', 'narahubung.proposal_reject')->count())->toBe(1);
        expect(fn () => app(DecideNarahubungProposal::class)->handle($this->ap, $usulan->fresh(), true))->toThrow(ValidationException::class);
    });

    test('AC-SCH-23 another branch admin or a mentor cannot decide', function () {
        $usulan = app(ProposeNarahubungChange::class)->handle($this->mentor, $this->sekolah, ['nama' => 'X', 'nomor_wa' => '08123456']);

        expect(fn () => app(DecideNarahubungProposal::class)->handle($this->acB, $usulan, true))->toThrow(AuthorizationException::class)
            ->and(fn () => app(DecideNarahubungProposal::class)->handle($this->mentor, $usulan, true))->toThrow(AuthorizationException::class)
            ->and(fn () => app(DecideNarahubungProposal::class)->handle($this->sa, $usulan, true))->toThrow(AuthorizationException::class);
        expect($usulan->fresh()->status)->toBe(NarahubungUsulan::DIAJUKAN);
    });
});

describe('mentor assignment (BR-MNT-01/03/05, A-02)', function () {
    test('BR-MNT-03 replacing the mentor ends the old assignment and keeps history', function () {
        $lama = $this->sekolah->penugasanAktif;

        app(AssignMentor::class)->handle($this->ap, $this->sekolah, $this->mentorLain->id, 'Mentor pindah tugas');

        expect($lama->fresh()->selesai)->not->toBeNull()
            ->and($lama->fresh()->alasan_selesai)->toBe('Mentor pindah tugas')
            ->and($this->sekolah->fresh()->penugasanAktif->mentor_id)->toBe($this->mentorLain->id)
            ->and(PenugasanMentor::where('sekolah_id', $this->sekolah->id)->count())->toBe(2)
            ->and($this->mentorLain->notifications()->count())->toBe(1)
            ->and(AuditLog::where('aksi', 'penugasan.assign')->count())->toBe(1);
    });

    test('BR-MNT-02 a mentor may handle schools of any branch', function () {
        $sekolahB = Fixtures::sekolah($this->cabangB);
        app(AssignMentor::class)->handle($this->ap, $sekolahB, $this->mentor->id);

        expect($sekolahB->fresh()->penugasanAktif->mentor_id)->toBe($this->mentor->id);
    });

    test('A-02 only superadmin and admin pusat assign, and only active mentors', function () {
        expect(fn () => app(AssignMentor::class)->handle($this->acA, $this->sekolah, $this->mentorLain->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AssignMentor::class)->handle($this->mentor, $this->sekolah, $this->mentorLain->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AssignMentor::class)->handle($this->ap, $this->sekolah, $this->acA->id))->toThrow(ValidationException::class)
            ->and(fn () => app(AssignMentor::class)->handle($this->ap, $this->sekolah, $this->mentor->id))->toThrow(ValidationException::class);

        $nonaktif = User::factory()->mentor()->nonaktif()->create();
        expect(fn () => app(AssignMentor::class)->handle($this->sa, $this->sekolah, $nonaktif->id))->toThrow(ValidationException::class);
    });
});

describe('MOU (owner decision D-03)', function () {
    test('admin pusat records and revokes the MOU document with audit', function () {
        app(RecordSekolahMou::class)->handle($this->ap, $this->sekolah, 'https://drive.example.test/mou-1.pdf', now()->subDay()->toDateString());

        $fresh = $this->sekolah->fresh();
        expect($fresh->isMou())->toBeTrue()->and($fresh->mou_dicatat_oleh)->toBe($this->ap->id);

        expect(fn () => app(RecordSekolahMou::class)->revoke($this->ap, $fresh, ''))->toThrow(ValidationException::class);
        app(RecordSekolahMou::class)->revoke($this->ap, $fresh, 'Dokumen salah sekolah');

        expect($this->sekolah->fresh()->isMou())->toBeFalse()
            ->and(AuditLog::whereIn('aksi', ['sekolah.mou', 'sekolah.mou_batal'])->count())->toBe(2);
    });

    test('only admin pusat may record the MOU and the link must be a URL', function () {
        foreach ([$this->sa, $this->acA, $this->mentor] as $user) {
            expect(fn () => app(RecordSekolahMou::class)->handle($user, $this->sekolah, 'https://x.test/mou.pdf'))->toThrow(AuthorizationException::class);
        }

        expect(fn () => app(RecordSekolahMou::class)->handle($this->ap, $this->sekolah, 'bukan-url'))->toThrow(ValidationException::class)
            ->and(fn () => app(RecordSekolahMou::class)->handle($this->ap, $this->sekolah, 'javascript:alert(1)'))->toThrow(ValidationException::class);
    });
});

describe('derived overall status (D-02), TL progress and certificates (PRD 15.4/15.5)', function () {
    test('status is Belum dimulai without running activity, Sedang berlangsung once one runs, Sudah selesai at Selesai Program', function () {
        Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::Awal->value, 'status' => StatusKegiatan::BelumDimulai->value]);
        expect(Sekolah::withRingkasan()->find($this->sekolah->id)->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai);

        Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::TindakLanjut->value, 'status' => StatusKegiatan::Selesai->value]);
        Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::TindakLanjut->value, 'status' => StatusKegiatan::BelumDimulai->value]);
        Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::TindakLanjut->value, 'status' => StatusKegiatan::Dibatalkan->value]);

        $s = Sekolah::withRingkasan()->find($this->sekolah->id);
        expect($s->statusPelatihan())->toBe(StatusPelatihan::SedangBerlangsung)
            ->and($s->progresTl())->toBe('1/2 sesi selesai');

        $this->sekolah->forceFill(['posisi_tahap' => PosisiTahap::SelesaiProgram->value])->save();
        expect(Sekolah::withRingkasan()->find($this->sekolah->id)->statusPelatihan())->toBe(StatusPelatihan::SudahSelesai);
    });

    test('the status filter scope matches the derived status', function () {
        $berjalan = Fixtures::sekolah($this->cabangA, $this->mentor);
        Fixtures::kegiatan($berjalan, $this->mentor, ['status' => StatusKegiatan::SedangBerlangsung->value]);

        expect(Sekolah::whereStatusPelatihan(StatusPelatihan::SedangBerlangsung)->pluck('id')->all())->toBe([$berjalan->id])
            ->and(Sekolah::whereStatusPelatihan(StatusPelatihan::BelumDimulai)->pluck('id')->all())->toBe([$this->sekolah->id]);
    });

    test('certificate status: Belum Tersedia, Menunggu Penerbitan, Tersedia', function () {
        expect($this->sekolah->statusSertifikat(Tahap::Awal))->toBe(StatusSertifikat::BelumBerlaku);

        $awal = Fixtures::kegiatan($this->sekolah, $this->mentor, ['status' => StatusKegiatan::Selesai->value, 'tanggal' => now()->subDay()->toDateString()]);
        $sertifikat = Sertifikat::create(['sekolah_id' => $this->sekolah->id, 'tahap' => Tahap::Awal->value, 'kegiatan_acuan_id' => $awal->id]);
        expect($this->sekolah->fresh()->statusSertifikat(Tahap::Awal))->toBe(StatusSertifikat::MenungguPenerbitan);

        $sertifikat->update(['link' => 'https://drive.example.test/sert.pdf']);
        expect($this->sekolah->fresh()->statusSertifikat(Tahap::Awal))->toBe(StatusSertifikat::Tersedia)
            ->and($this->sekolah->fresh()->statusSertifikat(Tahap::TindakLanjut))->toBe(StatusSertifikat::BelumBerlaku);
    });
});
