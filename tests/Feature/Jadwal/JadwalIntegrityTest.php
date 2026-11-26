<?php

/*
 * Fase 4 hardening: numbering and locking, optimistic locks, notification delivery, branch isolation
 * through direct calls, school status through the activity lifecycle, last-session rules and the
 * append-only schedule history.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Jadwal\Actions\AssignPelaksanaKegiatan;
use App\Domain\Jadwal\Actions\CreateJadwalAwal;
use App\Domain\Jadwal\Actions\CreateSesiTindakLanjut;
use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Actions\UpdateKegiatanDetail;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Laporan\Actions\ReportKegiatan;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Rangkaian\Models\RangkaianTlRiwayat;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\Tahap;
use App\Filament\Admin\Resources\Sekolahs\Pages\ViewSekolah;
use App\Filament\Admin\Resources\Sekolahs\RelationManagers\KegiatanTlRelationManager;
use App\Models\User;
use App\Support\Progress\ProgressRecalculator;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\Fixtures;

/** A failing statement aborts a PostgreSQL transaction; a savepoint keeps the test transaction usable. */
function dbRejects(Closure $statement): void
{
    expect(fn () => DB::transaction($statement))->toThrow(QueryException::class);
}

function refreshed(Sekolah $sekolah): Sekolah
{
    return Sekolah::query()->withCount(Sekolah::ringkasanCounts())->findOrFail($sekolah->id);
}

beforeEach(function () {
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();
    $this->ap = User::factory()->adminPusat()->create();
    $this->acA = User::factory()->adminCabang($this->cabangA)->create();
    $this->acB = User::factory()->adminCabang($this->cabangB)->create();
    $this->mentor = User::factory()->mentor()->create();
    $this->mentorLain = User::factory()->mentor()->create();

    $this->jenis = JenisKegiatan::create(['nama' => 'Pelatihan']);
    $this->online = MetodeKegiatan::create(['nama' => 'Online', 'butuh_tautan' => true]);
    $this->offline = MetodeKegiatan::create(['nama' => 'Offline', 'butuh_lokasi' => true]);
    AppSetting::create(['key' => AppSetting::LOKASI_KAMPUS_PUSAT, 'value' => 'Kampus Pusat']);

    $this->tlInput = fn (array $o = []) => $o + [
        'tanggal' => now()->addWeeks(2)->toDateString(), 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00',
        'jenis_kegiatan_id' => $this->jenis->id, 'metode_id' => $this->online->id, 'tautan_pertemuan' => 'https://meet.example.test/x',
    ];
    $this->awalInput = fn (array $o = []) => $o + [
        'tanggal' => now()->subDay()->toDateString(), 'waktu_mulai' => '09:00', 'waktu_selesai' => '12:00', 'jenis_kegiatan_id' => $this->jenis->id,
    ];
    $this->mitra = function (?Cabang $cabang = null) {
        $sekolah = Fixtures::sekolah($cabang ?? $this->cabangA, $this->mentor, ['posisi_tahap' => PosisiTahap::MitraBelumTl]);
        Fixtures::kegiatan($sekolah, $this->mentor, ['tahap' => Tahap::Awal, 'urutan' => 1, 'nama_sesi' => 'Pelatihan Awal', 'status' => StatusKegiatan::Selesai, 'tanggal' => now()->subWeek()->toDateString()]);

        return $sekolah;
    };
    $this->sesi = fn (Sekolah $s, StatusKegiatan $status, bool $terakhir = false) => Fixtures::kegiatan($s, $this->mentor, [
        'status' => $status, 'is_sesi_terakhir' => $terakhir, 'tanggal' => now()->subDays(2)->toDateString(),
    ]);
});

describe('numbering is safe against concurrent requests', function () {
    test('creating a session locks the school row before reading the next number', function () {
        $sekolah = ($this->mitra)();
        $sql = [];
        DB::listen(function ($q) use (&$sql) {
            $sql[] = strtolower($q->sql);
        });

        app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        $lock = collect($sql)->search(fn ($s) => str_contains($s, 'from "sekolah"') && str_contains($s, 'for update'));
        $max = collect($sql)->search(fn ($s) => str_contains($s, 'max("urutan")'));

        expect($lock)->not->toBeFalse()->and($max)->not->toBeFalse()->and($lock)->toBeLessThan($max);
    });

    test('the database itself rejects a duplicate session number or a second active initial training', function () {
        $sekolah = ($this->mitra)();
        Fixtures::kegiatan($sekolah, $this->mentor, ['urutan' => 7]);

        dbRejects(fn () => Fixtures::kegiatan($sekolah, $this->mentor, ['urutan' => 7]));
        // The same number is fine for another school and for the other stage.
        Fixtures::kegiatan(($this->mitra)(), $this->mentor, ['urutan' => 7]);
        Fixtures::kegiatan($sekolah, $this->mentor, ['urutan' => 7, 'tahap' => Tahap::Awal, 'status' => StatusKegiatan::Dibatalkan]);

        dbRejects(fn () => Fixtures::kegiatan($sekolah, $this->mentor, ['tahap' => Tahap::Awal, 'urutan' => 99]));
    });
});

describe('optimistic lock (E9)', function () {
    test('a stale save cannot overwrite a newer one, and the newer value survives', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $dibukaA = Kegiatan::find($sesi->id);
        $dibukaB = Kegiatan::find($sesi->id);

        app(UpdateKegiatanDetail::class)->handle($this->ap, $dibukaA, ['keterangan' => 'dari A'], $dibukaA->version);

        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->acA, $dibukaB, ['keterangan' => 'dari B'], $dibukaB->version))
            ->toThrow(ValidationException::class, 'diubah pengguna lain');
        expect($sesi->fresh()->keterangan)->toBe('dari A')->and($sesi->fresh()->version)->toBe(2);
    });

    test('a reschedule makes an older form of any other action stale as well', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $versiLama = $sesi->version;

        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => now()->addMonth()->toDateString()], 'Libur', $versiLama);

        expect(fn () => app(RescheduleKegiatan::class)->handle($this->acA, $sesi, ['waktu_mulai' => '08:00', 'waktu_selesai' => '09:00'], 'Lain', $versiLama))
            ->toThrow(ValidationException::class, 'diubah pengguna lain');
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->ap, $sesi, $this->mentorLain->id, $versiLama))
            ->toThrow(ValidationException::class, 'diubah pengguna lain');
        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->ap, $sesi, ['keterangan' => 'x'], $versiLama))
            ->toThrow(ValidationException::class, 'diubah pengguna lain');

        $fresh = $sesi->fresh();
        expect($fresh->mentor_pelaksana_id)->toBe($this->mentor->id)->and($fresh->keterangan)->toBeNull()
            ->and($fresh->riwayatJadwal()->count())->toBe(1);
    });

    test('the table form sends the loaded version, so a stale form is refused in the UI too', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $this->actingAs($this->ap);
        Filament::setCurrentPanel('admin');

        Livewire::test(KegiatanTlRelationManager::class, ['ownerRecord' => $sekolah, 'pageClass' => ViewSekolah::class])
            ->mountTableAction('ubahDetail', $sesi)
            ->assertTableActionDataSet(['version' => 1]);

        // Someone else saves in the meantime.
        app(UpdateKegiatanDetail::class)->handle($this->acA, $sesi, ['keterangan' => 'orang lain'], 1);

        Livewire::test(KegiatanTlRelationManager::class, ['ownerRecord' => $sekolah, 'pageClass' => ViewSekolah::class])
            ->callTableAction('ubahDetail', $sesi, data: ['nama_sesi' => 'Timpa', 'jenis_kegiatan_id' => $this->jenis->id, 'keterangan' => 'timpa', 'version' => 1])
            ->assertNotified('Tidak dapat disimpan');

        expect($sesi->fresh()->keterangan)->toBe('orang lain')->and($sesi->fresh()->nama_sesi)->toBe('Sesi 1');
    });
});

describe('notifications are sent once and never to the actor', function () {
    test('a new schedule reaches each recipient exactly once, also when the action runs inside a larger transaction', function () {
        $sekolah = ($this->mitra)();

        DB::transaction(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)()));

        $dibuat = fn (User $u) => $u->notifications()->where('data->title', 'Jadwal baru')->count();
        expect($dibuat($this->mentor))->toBe(1)->and($dibuat($this->acA))->toBe(1)
            ->and($dibuat($this->ap))->toBe(0) // the actor
            ->and($dibuat($this->acB))->toBe(0)->and($dibuat($this->mentorLain))->toBe(0);
    });

    test('the executing mentor who creates the session is not notified, the admins are', function () {
        $sekolah = ($this->mitra)();

        app(CreateSesiTindakLanjut::class)->handle($this->mentor, $sekolah, ($this->tlInput)());

        expect($this->mentor->notifications()->count())->toBe(0)
            ->and($this->ap->notifications()->where('data->title', 'Jadwal baru')->count())->toBe(1)
            ->and($this->acA->notifications()->where('data->title', 'Jadwal baru')->count())->toBe(1);
    });

    test('each reschedule notifies once per recipient; the actor never receives it', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        $diubah = fn (User $u) => $u->notifications()->where('data->title', 'Jadwal diubah')->count();

        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => now()->addWeeks(4)->toDateString()], 'Ujian');
        expect($diubah($this->ap))->toBe(1)->and($diubah($this->acA))->toBe(1)->and($diubah($this->mentor))->toBe(0)->and($diubah($this->acB))->toBe(0);

        app(RescheduleKegiatan::class)->handle($this->acA, $sesi->fresh(), ['tanggal' => now()->addWeeks(5)->toDateString()], 'Permintaan');
        // Totals so far: the mentor's change reached AP and AC (1 each); AC's own change reached only the mentor.
        expect($diubah($this->mentor))->toBe(1)->and($diubah($this->acA))->toBe(1)->and($diubah($this->ap))->toBe(1);
    });

    test('a failed action sends nothing', function () {
        $sekolah = Fixtures::sekolah($this->cabangA, $this->mentor); // Calon Mitra: session refused

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)()))->toThrow(ValidationException::class);

        expect(DB::table('notifications')->count())->toBe(0);
    });
});

describe('branch isolation through direct calls', function () {
    test('Admin Cabang of another branch is refused by every schedule action', function () {
        $sekolah = ($this->mitra)();
        $sesi = Fixtures::kegiatan($sekolah, $this->mentor, ['urutan' => 5]);
        $baru = Fixtures::sekolah($this->cabangA, $this->mentor);

        expect(fn () => app(CreateJadwalAwal::class)->handle($this->acB, $baru, ($this->awalInput)()))->toThrow(AuthorizationException::class);
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->acB, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);
        expect(fn () => app(RescheduleKegiatan::class)->handle($this->acB, $sesi, ['tanggal' => now()->addMonth()->toDateString()], 'x'))->toThrow(AuthorizationException::class);
        expect(fn () => app(UpdateKegiatanDetail::class)->handle($this->acB, $sesi, ['keterangan' => 'x']))->toThrow(AuthorizationException::class);
        expect(fn () => app(AssignPelaksanaKegiatan::class)->handle($this->acB, $sesi, $this->mentorLain->id))->toThrow(AuthorizationException::class);

        expect($sesi->fresh()->version)->toBe(1)->and(Kegiatan::where('sekolah_id', $sekolah->id)->count())->toBe(2)
            ->and($baru->kegiatan()->count())->toBe(0)
            ->and(AuditLog::whereIn('aksi', ['kegiatan.create', 'kegiatan.reschedule', 'kegiatan.update_detail'])->count())->toBe(0);
    });

    test('K4-01 Admin Cabang manages the schedule of its own branch only', function () {
        $sekolahA = ($this->mitra)();
        $sekolahB = ($this->mitra)($this->cabangB);

        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->acA, $sekolahA, ($this->tlInput)());
        app(RescheduleKegiatan::class)->handle($this->acA, $sesi, ['tanggal' => now()->addMonth()->toDateString()], 'Permintaan sekolah');
        expect($sesi->fresh()->riwayatJadwal()->count())->toBe(1);

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->acA, $sekolahB, ($this->tlInput)()))->toThrow(AuthorizationException::class);
    });

    test('HTTP: the other branch gets 404 for the school page and finds nothing in the schedule list', function () {
        $sekolah = ($this->mitra)();
        $sekolah->forceFill(['nama' => 'SMA Rahasia Cabang A'])->save();
        Fixtures::kegiatan($sekolah, $this->mentor, ['urutan' => 5]);

        $this->actingAs($this->acB)->get("/admin/sekolah/{$sekolah->id}")->assertNotFound();
        $this->actingAs($this->acB)->get('/admin/jadwal')->assertOk()->assertDontSee('SMA Rahasia Cabang A');
        $this->actingAs($this->acA)->get('/admin/jadwal')->assertOk()->assertSee('SMA Rahasia Cabang A');
    });

    test('a forged component call for another branch school creates nothing', function () {
        $sekolah = ($this->mitra)();
        $this->actingAs($this->acB);
        Filament::setCurrentPanel('admin');

        try {
            Livewire::test(KegiatanTlRelationManager::class, ['ownerRecord' => $sekolah, 'pageClass' => ViewSekolah::class])
                ->callTableAction('tambahSesi', data: ($this->tlInput)());
        } catch (Throwable) {
            // Refused (403 or hidden action): either way nothing is written.
        }

        expect(Kegiatan::where('sekolah_id', $sekolah->id)->where('tahap', 'tindak_lanjut')->count())->toBe(0);
    });
});

describe('school status through the activity lifecycle', function () {
    test('scheduled, rescheduled, running, finished and cancelled activities move the school as the PRD defines', function () {
        $sekolah = Fixtures::sekolah($this->cabangA, $this->mentor);

        // Scheduled: still Calon Mitra, overall status Belum dimulai.
        $awal = app(CreateJadwalAwal::class)->handle($this->ap, $sekolah, ($this->awalInput)());
        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::CalonMitra)
            ->and(refreshed($sekolah)->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai);

        // Rescheduled: nothing changes except the history.
        app(RescheduleKegiatan::class)->handle($this->mentor, $awal, ['tanggal' => now()->subDays(3)->toDateString()], 'Sekolah meminta');
        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::CalonMitra)
            ->and(refreshed($sekolah)->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai)
            ->and($awal->fresh()->status)->toBe(StatusKegiatan::BelumDimulai);

        // Running: overall status Sedang berlangsung.
        $awal = app(ReportKegiatan::class)->handle($this->mentor, $awal->fresh(), StatusKegiatan::SedangBerlangsung, 'Mulai');
        expect(refreshed($sekolah)->statusPelatihan())->toBe(StatusPelatihan::SedangBerlangsung)
            ->and(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::CalonMitra);

        // Finished: the school becomes a partner and the initial certificate record exists.
        app(ReportKegiatan::class)->handle($this->mentor, $awal->fresh(), StatusKegiatan::Selesai, 'Selesai');
        $s = refreshed($sekolah);
        expect($s->posisi_tahap)->toBe(PosisiTahap::MitraBelumTl)->and($s->tanggal_mitra)->not->toBeNull()
            ->and($s->statusPelatihan())->toBe(StatusPelatihan::SedangBerlangsung)
            ->and(Sertifikat::where('sekolah_id', $sekolah->id)->where('tahap', 'awal')->exists())->toBeTrue();

        // First follow-up session: Mitra – Tindak Lanjut. Cancelling it brings the school back.
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['tanggal' => now()->subDay()->toDateString()]));
        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraTl);

        app(ReportKegiatan::class)->handle($this->mentor, $sesi->fresh(), StatusKegiatan::Dibatalkan, 'Sekolah libur');
        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraBelumTl)
            ->and($sesi->fresh()->status)->toBe(StatusKegiatan::Dibatalkan);
    });

    test('a cancelled initial training leaves the school a candidate; a new one can be scheduled and the old one stays', function () {
        $sekolah = Fixtures::sekolah($this->cabangA, $this->mentor);
        $lama = app(CreateJadwalAwal::class)->handle($this->ap, $sekolah, ($this->awalInput)());

        app(ReportKegiatan::class)->handle($this->mentor, $lama, StatusKegiatan::Dibatalkan, 'Sekolah mundur');

        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::CalonMitra)
            ->and(refreshed($sekolah)->statusPelatihan())->toBe(StatusPelatihan::BelumDimulai);

        $baru = app(CreateJadwalAwal::class)->handle($this->ap, $sekolah, ($this->awalInput)());
        expect(Kegiatan::where('sekolah_id', $sekolah->id)->count())->toBe(2)->and($lama->fresh()->status)->toBe(StatusKegiatan::Dibatalkan)->and($baru->urutan)->toBe(2);
    });
});

describe('last session and series completion (BR-TL-06/07/12)', function () {
    test('AC-JTL-07 last session set and every active session finished completes the series', function () {
        $sekolah = ($this->mitra)();
        ($this->sesi)($sekolah, StatusKegiatan::Selesai);
        $terakhir = ($this->sesi)($sekolah, StatusKegiatan::Selesai, true);

        ProgressRecalculator::forSekolah($sekolah);

        $s = refreshed($sekolah);
        expect($s->posisi_tahap)->toBe(PosisiTahap::SelesaiProgram)->and($s->statusPelatihan())->toBe(StatusPelatihan::SudahSelesai)
            ->and($s->tl_selesai_at)->not->toBeNull()
            ->and(Sertifikat::where('sekolah_id', $sekolah->id)->where('tahap', 'tindak_lanjut')->value('kegiatan_acuan_id'))->toBe($terakhir->id)
            ->and(RangkaianTlRiwayat::where('sekolah_id', $sekolah->id)->where('aksi', 'selesai_otomatis')->count())->toBe(1);

        // A finished series refuses new sessions until it is reopened.
        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)()))->toThrow(ValidationException::class);
    });

    test('AC-JTL-07 a last session that is Selesai does not finish the series while another session is open', function () {
        $sekolah = ($this->mitra)();
        ($this->sesi)($sekolah, StatusKegiatan::BelumDimulai);
        ($this->sesi)($sekolah, StatusKegiatan::Selesai, true);

        ProgressRecalculator::forSekolah($sekolah);

        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraTl)->and(refreshed($sekolah)->tl_selesai_at)->toBeNull();
    });

    test('a last session that is itself not finished does not finish the series', function () {
        foreach ([StatusKegiatan::BelumDimulai, StatusKegiatan::SedangBerlangsung] as $status) {
            $sekolah = ($this->mitra)();
            ($this->sesi)($sekolah, StatusKegiatan::Selesai);
            ($this->sesi)($sekolah, $status, true);

            ProgressRecalculator::forSekolah($sekolah);

            expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraTl);
        }
    });

    test('E22 every session finished but no last session set: not finished', function () {
        $sekolah = ($this->mitra)();
        ($this->sesi)($sekolah, StatusKegiatan::Selesai);
        ($this->sesi)($sekolah, StatusKegiatan::Selesai);

        ProgressRecalculator::forSekolah($sekolah);

        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraTl);
    });

    test('AC-JTL-14 a cancelled session that is not the last one does not block completion', function () {
        $sekolah = ($this->mitra)();
        foreach (range(1, 3) as $i) {
            ($this->sesi)($sekolah, StatusKegiatan::Selesai);
        }
        ($this->sesi)($sekolah, StatusKegiatan::Dibatalkan);
        ($this->sesi)($sekolah, StatusKegiatan::Selesai, true);

        ProgressRecalculator::forSekolah($sekolah);

        expect(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::SelesaiProgram);
    });

    test('AC-JTL-15 a cancelled last session loses its mark and the series is not finished', function () {
        $sekolah = ($this->mitra)();
        ($this->sesi)($sekolah, StatusKegiatan::Selesai);
        $terakhir = ($this->sesi)($sekolah, StatusKegiatan::Dibatalkan, true);

        $hasil = ProgressRecalculator::forSekolah($sekolah);

        expect($hasil->sesiTerakhirDibatalkan)->toBeTrue()
            ->and($terakhir->fresh()->is_sesi_terakhir)->toBeFalse()
            ->and(refreshed($sekolah)->posisi_tahap)->toBe(PosisiTahap::MitraTl)
            ->and(RangkaianTlRiwayat::where('sekolah_id', $sekolah->id)->where('aksi', 'sesi_terakhir_dibatalkan')->count())->toBe(1);
    });

    test('BR-TL-07 the database allows one last-session mark per school, on follow-up sessions only', function () {
        $sekolah = ($this->mitra)();
        $satu = ($this->sesi)($sekolah, StatusKegiatan::Selesai, true);
        $dua = ($this->sesi)($sekolah, StatusKegiatan::Selesai);

        dbRejects(fn () => $dua->forceFill(['is_sesi_terakhir' => true])->save());
        dbRejects(fn () => Kegiatan::where('sekolah_id', $sekolah->id)->where('tahap', 'awal')->update(['is_sesi_terakhir' => true]));
        expect($satu->fresh()->is_sesi_terakhir)->toBeTrue();
    });

    test('a mentor adding a session after the last one is set is refused; Admin Pusat adding one keeps the mark on the older session', function () {
        $sekolah = ($this->mitra)();
        $terakhir = ($this->sesi)($sekolah, StatusKegiatan::Selesai, true);
        ProgressRecalculator::forSekolah($sekolah);
        $sekolah->forceFill(['posisi_tahap' => PosisiTahap::MitraTl, 'tl_selesai_at' => null])->save();

        expect(fn () => app(CreateSesiTindakLanjut::class)->handle($this->mentor, $sekolah, ($this->tlInput)()))->toThrow(AuthorizationException::class);

        $baru = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        expect($baru->is_sesi_terakhir)->toBeFalse()->and($terakhir->fresh()->is_sesi_terakhir)->toBeTrue();
    });
});

describe('schedule history is kept (BR-ACT-06)', function () {
    test('every reschedule adds a numbered version with the previous and new values, who and why', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['tanggal' => '2027-01-10']));

        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => '2027-01-17'], 'Ujian');
        app(RescheduleKegiatan::class)->handle($this->acA, $sesi->fresh(), ['tanggal' => '2027-01-24', 'waktu_mulai' => '10:00', 'waktu_selesai' => '11:00'], 'Libur nasional');
        app(RescheduleKegiatan::class)->handle($this->ap, $sesi->fresh(), ['tanggal' => '2027-02-01'], 'Permintaan kepala sekolah');

        $riwayat = $sesi->fresh()->riwayatJadwal()->get();
        expect($riwayat->pluck('versi')->all())->toBe([1, 2, 3])
            ->and($riwayat->pluck('sebelum.tanggal')->all())->toBe(['2027-01-10', '2027-01-17', '2027-01-24'])
            ->and($riwayat->pluck('sesudah.tanggal')->all())->toBe(['2027-01-17', '2027-01-24', '2027-02-01'])
            ->and($riwayat->pluck('alasan')->all())->toBe(['Ujian', 'Libur nasional', 'Permintaan kepala sekolah'])
            ->and($riwayat->pluck('diubah_oleh')->all())->toBe([$this->mentor->id, $this->acA->id, $this->ap->id])
            ->and(AuditLog::where('aksi', 'kegiatan.reschedule')->count())->toBe(3);
    });

    test('a refused reschedule leaves no history and no version bump', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());

        expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['waktu_mulai' => '15:00', 'waktu_selesai' => '14:00'], 'Salah'))->toThrow(ValidationException::class);
        expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => now()->addMonth()->toDateString()], ''))->toThrow(ValidationException::class);

        expect($sesi->fresh()->riwayatJadwal()->count())->toBe(0)->and($sesi->fresh()->version)->toBe(1);
    });

    test('history rows cannot be changed or deleted, not even with direct SQL, and an activity with history cannot be deleted', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)());
        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => now()->addMonth()->toDateString()], 'Ujian');

        dbRejects(fn () => DB::table('kegiatan_jadwal_riwayat')->update(['alasan' => 'diubah']));
        dbRejects(fn () => DB::table('kegiatan_jadwal_riwayat')->delete());
        dbRejects(fn () => DB::table('kegiatan')->where('id', $sesi->id)->delete());

        expect($sesi->fresh()->riwayatJadwal()->sole()->alasan)->toBe('Ujian');
    });

    test('history survives the activity being finished or cancelled', function () {
        $sekolah = ($this->mitra)();
        $sesi = app(CreateSesiTindakLanjut::class)->handle($this->ap, $sekolah, ($this->tlInput)(['tanggal' => now()->addDay()->toDateString()]));
        app(RescheduleKegiatan::class)->handle($this->mentor, $sesi, ['tanggal' => now()->subDay()->toDateString()], 'Dimajukan');

        app(ReportKegiatan::class)->handle($this->mentor, $sesi->fresh(), StatusKegiatan::Dibatalkan, 'Batal');

        expect($sesi->fresh()->riwayatJadwal()->count())->toBe(1)
            ->and(Kegiatan::where('sekolah_id', $sekolah->id)->where('status', 'dibatalkan')->count())->toBe(1);
        // A cancelled activity cannot be rescheduled any more (BR-ACT-11).
        expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $sesi->fresh(), ['tanggal' => now()->addMonth()->toDateString()], 'x'))->toThrow(AuthorizationException::class);
    });
});
