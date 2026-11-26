<?php

/*
 * Fase 4 domain actions: initial training schedule (FR-JDA, BR-AWL, AC-JDA-01..06/08), follow-up
 * sessions (FR-JTL, BR-TL, AC-JTL-01..05/08/09/10/12/16), executor assignment, schedule history and
 * the link from activity progress to the school's overall status.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Jadwal\Actions\AssignPelaksanaKegiatan;
use App\Domain\Jadwal\Actions\CreateJadwalAwal;
use App\Domain\Jadwal\Actions\CreateSesiTindakLanjut;
use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Actions\UpdateKegiatanDetail;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\StatusPelatihan;
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

    $this->jenis = JenisKegiatan::create(['nama' => 'Pelatihan']);
    $this->online = MetodeKegiatan::create(['nama' => 'Online', 'butuh_tautan' => true]);
    $this->offline = MetodeKegiatan::create(['nama' => 'Offline', 'butuh_lokasi' => true]);
    AppSetting::create(['key' => AppSetting::LOKASI_KAMPUS_PUSAT, 'value' => 'Kampus Pusat, Semarang']);

    $this->awalInput = fn (array $o = []) => $o + [
        'tanggal' => now()->addWeek()->toDateString(),
        'waktu_mulai' => '09:00',
        'waktu_selesai' => '12:00',
        'jenis_kegiatan_id' => $this->jenis->id,
    ];
    $this->tlInput = fn (array $o = []) => $o + [
        'tanggal' => now()->addWeeks(3)->toDateString(),
        'waktu_mulai' => '13:00',
        'waktu_selesai' => '15:00',
        'jenis_kegiatan_id' => $this->jenis->id,
        'metode_id' => $this->online->id,
        'tautan_pertemuan' => 'https://meet.example.test/tl',
    ];

    // A school whose initial training is done, i.e. a partner that may have follow-up sessions.
    $this->mitra = function (?User $mentor = null) {
        $sekolah = Fixtures::sekolah($this->cabangA, $mentor ?? $this->mentor, ['posisi_tahap' => PosisiTahap::MitraBelumTl]);
        Fixtures::kegiatan($sekolah, $mentor ?? $this->mentor, ['tahap' => Tahap::Awal, 'urutan' => 1, 'nama_sesi' => 'Pelatihan Awal', 'status' => StatusKegiatan::Selesai]);

        return $sekolah;
    };
});

describe('initial training (FR-JDA, BR-AWL)', function () {
    test('AC-JDA-01 admin creates a complete schedule as Belum Dimulai, audited, offline at the central campus', function () {
        $kegiatan = app(CreateJadwalAwal::class)->handle($this->acA, $this->sekolah, ($this->awalInput)(['keterangan' => 'Bawa laptop']));

        expect($kegiatan->status)->toBe(StatusKegiatan::BelumDimulai)
            ->and($kegiatan->tahap)->toBe(Tahap::Awal)
            ->and($kegiatan->urutan)->toBe(1)
            ->and($kegiatan->mentor_pelaksana_id)->toBe($this->mentor->id)
            ->and($kegiatan->pelaksana_ditunjuk_khusus)->toBeFalse()
            ->and($kegiatan->created_by)->toBe($this->acA->id)
            ->and(AuditLog::where('aksi', 'kegiatan.create')->sole()->user_id)->toBe($this->acA->id);
    });

    test('AC-JDA-04 method is fixed to Offline and the location to the central campus, with an optional room', function () {
        $kegiatan = app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah,
            ($this->awalInput)(['metode_id' => $this->online->id, 'lokasi' => 'Rumah', 'detail_lokasi' => 'Ruang Lab 2']));

        expect($kegiatan->metode_id)->toBe($this->offline->id)
            ->and($kegiatan->lokasi)->toBe('Kampus Pusat, Semarang — Ruang Lab 2')
            ->and($kegiatan->tautan_pertemuan)->toBeNull();
    });

    test('AC-JDA-02 a second non-cancelled initial training is refused', function () {
        app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)());

        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)()))
            ->toThrow(ValidationException::class, 'Sekolah sudah memiliki pelatihan awal');
        expect(Kegiatan::where('sekolah_id', $this->sekolah->id)->count())->toBe(1);
    });

    test('AC-JDA-08 a school whose initial training is Selesai cannot get another', function () {
        $sekolah = ($this->mitra)();

        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $sekolah, ($this->awalInput)()))
            ->toThrow(ValidationException::class, 'Sekolah sudah memiliki pelatihan awal');
    });

    test('AC-JDA-03 after a cancelled initial training a new activity is created and the old one stays', function () {
        $lama = app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)());
        $lama->forceFill(['status' => StatusKegiatan::Dibatalkan])->save();

        $baru = app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)());

        expect($baru->id)->not->toBe($lama->id)
            ->and($baru->urutan)->toBe(2)
            ->and($lama->fresh()->status)->toBe(StatusKegiatan::Dibatalkan)
            ->and(Kegiatan::where('sekolah_id', $this->sekolah->id)->count())->toBe(2);
    });

    test('AC-JDA-05 end time must be after start time', function () {
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)(['waktu_mulai' => '10:00', 'waktu_selesai' => '10:00'])))
            ->toThrow(ValidationException::class);
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)(['waktu_mulai' => '10:00', 'waktu_selesai' => '09:00'])))
            ->toThrow(ValidationException::class);
    });

    test('required fields are enforced', function () {
        foreach (['tanggal', 'waktu_mulai', 'waktu_selesai', 'jenis_kegiatan_id'] as $field) {
            $input = ($this->awalInput)();
            unset($input[$field]);

            expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, $input))->toThrow(ValidationException::class);
        }
    });

    test('AC-JDA-06 admin cabang cannot schedule for a school of another branch', function () {
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->acB, $this->sekolah, ($this->awalInput)()))
            ->toThrow(AuthorizationException::class);
        expect(Kegiatan::count())->toBe(0);
    });

    test('mentors cannot create the initial training', function () {
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->mentor, $this->sekolah, ($this->awalInput)()))
            ->toThrow(AuthorizationException::class);
    });

    test('a school without a responsible mentor cannot be scheduled (PRD 13.4 recommendation)', function () {
        $tanpaMentor = Fixtures::sekolah($this->cabangA);

        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $tanpaMentor, ($this->awalInput)()))
            ->toThrow(ValidationException::class, 'mentor penanggung jawab');
    });

    test('an archived school cannot be scheduled', function () {
        $this->sekolah->forceFill(['diarsipkan_at' => now(), 'alasan_arsip' => 'Tutup'])->save();

        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)()))
            ->toThrow(AuthorizationException::class);
    });

    test('Admin Pusat may designate another active mentor; admin cabang may not', function () {
        $kegiatan = app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)(['mentor_pelaksana_id' => $this->mentorLain->id]));

        expect($kegiatan->mentor_pelaksana_id)->toBe($this->mentorLain->id)
            ->and($kegiatan->pelaksana_ditunjuk_khusus)->toBeTrue();

        $lain = Fixtures::sekolah($this->cabangA, $this->mentor);
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->acA, $lain, ($this->awalInput)(['mentor_pelaksana_id' => $this->mentorLain->id])))
            ->toThrow(AuthorizationException::class);

        $nonaktif = User::factory()->mentor()->nonaktif()->create();
        expect(fn () => app(CreateJadwalAwal::class)->handle($this->ap, $lain, ($this->awalInput)(['mentor_pelaksana_id' => $nonaktif->id])))
            ->toThrow(ValidationException::class);
    });

    test('the executor, Admin Pusat and the branch admin are notified of a new schedule, the actor and other branches are not', function () {
        app(CreateJadwalAwal::class)->handle($this->acA, $this->sekolah, ($this->awalInput)());

        expect($this->mentor->notifications()->count())->toBe(1)
            ->and($this->ap->notifications()->count())->toBe(1)
            ->and($this->acA->notifications()->count())->toBe(0)
            ->and($this->acB->notifications()->count())->toBe(0)
            ->and($this->mentorLain->notifications()->count())->toBe(0);
    });
});

describe('follow-up sessions (FR-JTL, BR-TL)', function () {
    test('AC-JTL-01 a school that is still Calon Mitra cannot get a session', function () {
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $this->sekolah, ($this->tlInput)()))
            ->toThrow(ValidationException::class, 'setelah pelatihan awal selesai');

        // A planned (not yet Selesai) initial training is not enough either.
        app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)());
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $this->sekolah, ($this->tlInput)()))
            ->toThrow(ValidationException::class);
    });

    test('AC-JTL-02 the next session is numbered after the largest and named "Sesi 3"', function () {
        $sekolah = ($this->mitra)();
        $action = app(CreateSesiTindakLanjut::class);

        $action->handle($this->ap, $sekolah, ($this->tlInput)());
        $action->handle($this->ap, $sekolah, ($this->tlInput)());
        $ketiga = $action->handle($this->ap, $sekolah, ($this->tlInput)());

        expect($ketiga->urutan)->toBe(3)
            ->and($ketiga->nama_sesi)->toBe('Sesi 3')
            ->and($ketiga->tahap)->toBe(Tahap::TindakLanjut)
            ->and($ketiga->status)->toBe(StatusKegiatan::BelumDimulai);
    });

    test('a cancelled session keeps its number, so numbers are never reused', function () {
        $sekolah = ($this->mitra)();
        $action = app(CreateSesiTindakLanjut::class);
        $satu = $action->handle($this->ap, $sekolah, ($this->tlInput)());
        $satu->forceFill(['status' => StatusKegiatan::Dibatalkan])->save();

        expect($action->handle($this->ap, $sekolah, ($this->tlInput)())->urutan)->toBe(2);
    });

    test('AC-JTL-03 / AC-JTL-12 an uneven online/offline mix is accepted without a reason and shown as a reference', function () {
        $sekolah = ($this->mitra)();
        $action = app(CreateSesiTindakLanjut::class);

        foreach (range(1, 3) as $i) {
            $action->handle($this->ap, $sekolah, ($this->tlInput)());
        }
        $action->handle($this->ap, $sekolah, ($this->tlInput)(['metode_id' => $this->offline->id, 'lokasi' => 'SMAN 1', 'tautan_pertemuan' => null]));

        expect($sekolah->komposisiTl())->toBe('Online 3 · Offline 1 (acuan juklak 4+1)');
    });

    test('AC-JTL-04 seven sessions are accepted, no upper limit', function () {
        $sekolah = ($this->mitra)();

        foreach (range(1, 7) as $i) {
            app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        }

        expect($sekolah->kegiatan()->where('tahap', 'tindak_lanjut')->count())->toBe(7);
    });

    test('AC-JTL-05 renaming a session changes the name and keeps the number', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        app(UpdateKegiatanDetail::class)->handle($this->acA, $sesi, ['nama_sesi' => 'Workshop Offline'], $sesi->version);

        $fresh = $sesi->fresh();
        expect($fresh->nama_sesi)->toBe('Workshop Offline')
            ->and($fresh->urutan)->toBe(1)
            ->and($fresh->version)->toBe(2)
            ->and(AuditLog::where('aksi', 'kegiatan.update_detail')->exists())->toBeTrue();
    });

    test('mentors cannot rename sessions and a blank name is refused (C-04)', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->mentor, $sesi, ['nama_sesi' => 'X']))->toThrow(AuthorizationException::class);
        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->ap, $sesi, ['nama_sesi' => ' ']))->toThrow(ValidationException::class);
    });

    test('AC-JTL-09 offline needs a location and online needs a meeting link', function () {
        $sekolah = ($this->mitra)();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['metode_id' => $this->offline->id, 'tautan_pertemuan' => null])))
            ->toThrow(ValidationException::class, 'Lokasi wajib');
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['tautan_pertemuan' => null])))
            ->toThrow(ValidationException::class, 'Tautan pertemuan wajib');
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['tautan_pertemuan' => 'bukan url'])))
            ->toThrow(ValidationException::class);
        expect(Kegiatan::where('tahap', 'tindak_lanjut')->count())->toBe(0);
    });

    test('an online session does not keep a stray location and vice versa', function () {
        $sekolah = ($this->mitra)();

        $online = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['lokasi' => 'Abaikan']));
        $offline = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['metode_id' => $this->offline->id, 'lokasi' => 'SMAN 1', 'tautan_pertemuan' => 'https://abaikan.test']));

        expect($online->lokasi)->toBeNull()
            ->and($offline->tautan_pertemuan)->toBeNull()
            ->and($offline->lokasi)->toBe('SMAN 1');
    });

    test('AC-JTL-10 the responsible mentor adds a session; Admin Pusat and the branch admin are notified and it is audited', function () {
        $sekolah = ($this->mitra)();

        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->mentor, $sekolah, ($this->tlInput)());

        expect($sesi->mentor_pelaksana_id)->toBe($this->mentor->id)
            ->and($sesi->created_by)->toBe($this->mentor->id)
            ->and($this->ap->notifications()->count())->toBe(1)
            ->and($this->acA->notifications()->count())->toBe(1)
            ->and($this->acB->notifications()->count())->toBe(0)
            ->and($this->mentor->notifications()->count())->toBe(0)
            ->and(AuditLog::where('aksi', 'kegiatan.create')->where('role_saat_aksi', 'mentor')->exists())->toBeTrue();
    });

    test('BR-ACT-12 a mentor who is not responsible for the school cannot add sessions, nor can an out-of-scope admin', function () {
        $sekolah = ($this->mitra)();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->mentorLain, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->acB, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);
    });

    test('BR-TL-10 / AC-JTL-13 once the last session is set a mentor cannot add sessions but Admin Pusat can', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $sesi->forceFill(['is_sesi_terakhir' => true])->save();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->mentor, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->acA, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);

        expect(app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)())->urutan)->toBe(2);
    });

    test('BR-TL-12 a cancelled last session no longer blocks the mentor', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $sesi->forceFill(['is_sesi_terakhir' => true, 'status' => StatusKegiatan::Dibatalkan])->save();

        expect(app(CreateSesiTindakLanjut::class)->handle($this->mentor, $sekolah, ($this->tlInput)())->urutan)->toBe(2);
    });

    test('a finished series must be reopened before sessions are added', function () {
        $sekolah = ($this->mitra)();
        $sekolah->forceFill(['posisi_tahap' => PosisiTahap::SelesaiProgram])->save();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)()))
            ->toThrow(ValidationException::class, 'membukanya kembali');
    });

    test('an archived school cannot get sessions', function () {
        $sekolah = ($this->mitra)();
        $sekolah->forceFill(['diarsipkan_at' => now(), 'alasan_arsip' => 'Tutup'])->save();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);
    });

    test('AC-JTL-16 a cancelled session stays in the list with its status and notes', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['keterangan' => 'Sekolah libur']));
        $sesi->forceFill(['status' => StatusKegiatan::Dibatalkan])->save();

        $baris = $sekolah->kegiatan()->where('tahap', 'tindak_lanjut')->sole();
        expect($baris->status)->toBe(StatusKegiatan::Dibatalkan)->and($baris->keterangan)->toBe('Sekolah libur');
    });
});

describe('executor, schedule history and school status', function () {
    test('Admin Pusat designates another executor, which is audited and notified', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        app(AssignPelaksanaKegiatan::class)->handle($this->ap, $sesi, $this->mentorLain->id, $sesi->version);

        $fresh = $sesi->fresh();
        expect($fresh->mentor_pelaksana_id)->toBe($this->mentorLain->id)
            ->and($fresh->pelaksana_ditunjuk_khusus)->toBeTrue()
            ->and($this->mentorLain->notifications()->count())->toBe(1)
            ->and(AuditLog::where('aksi', 'kegiatan.assign_pelaksana')->sole()->sebelum['mentor_pelaksana_id'])->toBe($this->mentor->id);

        // Back to the responsible mentor: no longer a special designation.
        app(AssignPelaksanaKegiatan::class)->handle($this->ap, $fresh, $this->mentor->id);
        expect($sesi->fresh()->pelaksana_ditunjuk_khusus)->toBeFalse();
    });

    test('executor assignment is for Superadmin and Admin Pusat only, active mentors only, and not a no-op', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->acA, $sesi, $this->mentorLain->id))->toThrow(AuthorizationException::class);
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->mentor, $sesi, $this->mentorLain->id))->toThrow(AuthorizationException::class);
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->sa, $sesi, $this->ap->id))->toThrow(ValidationException::class);
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->sa, $sesi, $this->mentor->id))->toThrow(ValidationException::class);

        app(AssignPelaksanaKegiatan::class)->handle($this->sa, $sesi, $this->mentorLain->id);
        expect($sesi->fresh()->mentor_pelaksana_id)->toBe($this->mentorLain->id);
    });

    test('a finished activity cannot get a new executor', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $sesi->forceFill(['status' => StatusKegiatan::Selesai])->save();

        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->ap, $sesi, $this->mentorLain->id))->toThrow(AuthorizationException::class);
    });

    test('BR-ACT-06 / AC-JTL-08 the mentor reschedules a created session, history keeps the old version, admins are notified', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $lama = $sesi->tanggal->toDateString();
        $baru = now()->addWeeks(5)->toDateString();

        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => $baru], 'Sekolah ujian', $sesi->version);

        $riwayat = $sesi->fresh()->riwayatJadwal()->sole();
        expect($riwayat->sebelum['tanggal'])->toBe($lama)
            ->and($riwayat->sesudah['tanggal'])->toBe($baru)
            ->and($riwayat->diubah_oleh)->toBe($this->mentor->id)
            ->and($this->ap->notifications()->where('data->title', 'Jadwal diubah')->count())->toBe(1)
            ->and($this->acA->notifications()->where('data->title', 'Jadwal diubah')->count())->toBe(1)
            ->and($this->acB->notifications()->where('data->title', 'Jadwal diubah')->count())->toBe(0);
    });

    test('an admin rescheduling notifies the executing mentor', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        app(RescheduleKegiatan::class)->handle($this->acA, $sesi, ['waktu_mulai' => '14:00', 'waktu_selesai' => '16:00'], 'Permintaan sekolah');

        expect($this->mentor->notifications()->where('data->title', 'Jadwal diubah')->count())->toBe(1)
            ->and($this->acA->notifications()->where('data->title', 'Jadwal diubah')->count())->toBe(0);
    });

    test('a mentor who is neither responsible nor executing cannot reschedule (scope)', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentorLain, $sesi, ['tanggal' => now()->addMonth()->toDateString()], 'x'))
            ->toThrow(AuthorizationException::class);
    });

    test('E9 a stale version cannot change the detail or the executor', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        app(UpdateKegiatanDetail::class)->handle($this->ap, $sesi, ['keterangan' => 'a'], 1);

        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->ap, $sesi, ['keterangan' => 'b'], 1))->toThrow(ValidationException::class, 'diubah pengguna lain');
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->ap, $sesi, $this->mentorLain->id, 1))->toThrow(ValidationException::class, 'diubah pengguna lain');
    });

    test('activity progress drives the school status: scheduled is Belum dimulai, a running session makes it Sedang berlangsung', function () {
        $sekolah = Sekolah::query()->withCount(Sekolah::ringkasanCounts())->findOrFail($this->sekolah->id);
        expect($sekolah->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai);

        $awal = app(CreateJadwalAwal::class)->handle($this->ap, $this->sekolah, ($this->awalInput)());
        $sekolah = Sekolah::query()->withCount(Sekolah::ringkasanCounts())->findOrFail($this->sekolah->id);
        expect($sekolah->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai);

        $awal->forceFill(['status' => StatusKegiatan::SedangBerlangsung])->save();
        $sekolah = Sekolah::query()->withCount(Sekolah::ringkasanCounts())->findOrFail($this->sekolah->id);
        expect($sekolah->statusPelatihan())->toBe(StatusPelatihan::SedangBerlangsung);
    });
});
