<?php

/*
 * SP-05: domain actions are testable without booting Filament or Livewire.
 * These tests call the actions directly with a user, never through a panel.
 */

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Jadwal\Actions\RescheduleKegiatan;
use App\Domain\Jadwal\Events\JadwalDiubah;
use App\Domain\Laporan\Actions\ReportKegiatan;
use App\Domain\Laporan\Events\StatusKegiatanDiperbarui;
use App\Domain\Laporan\Models\LaporanRevisi;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->mentor = User::factory()->mentor()->create();
    $this->sekolah = Fixtures::sekolah($this->cabang, $this->mentor);
    $this->kegiatan = Fixtures::kegiatan($this->sekolah, $this->mentor);
});

test('panels are not booted by these tests', function () {
    expect(Filament\Facades\Filament::getCurrentPanel()?->getId())->not->toBe('mentor');
});

test('reschedule writes the schedule, a history version, an audit entry and an event', function () {
    Event::fake([JadwalDiubah::class]);
    $tanggalBaru = now()->addDays(14)->toDateString();

    app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan, ['tanggal' => $tanggalBaru, 'waktu_mulai' => '13:00', 'waktu_selesai' => '15:00'], 'Sekolah ujian', 1);

    $kegiatan = $this->kegiatan->fresh();
    $riwayat = $kegiatan->riwayatJadwal()->sole();
    $audit = AuditLog::where('aksi', 'kegiatan.reschedule')->sole();

    expect($kegiatan->tanggal->toDateString())->toBe($tanggalBaru)
        ->and($kegiatan->version)->toBe(2)
        ->and($riwayat->versi)->toBe(1)
        ->and($riwayat->alasan)->toBe('Sekolah ujian')
        ->and($riwayat->sesudah['waktu_mulai'])->toBe('13:00')
        ->and($riwayat->sebelum['tanggal'])->toBe($this->kegiatan->tanggal->toDateString())
        ->and($audit->user_id)->toBe($this->mentor->id)
        ->and($audit->role_saat_aksi)->toBe('mentor');

    Event::assertDispatched(JadwalDiubah::class, fn (JadwalDiubah $e) => $e->kegiatan->is($kegiatan));
});

test('reschedule requires a reason', function () {
    expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan, ['tanggal' => now()->addDays(3)->toDateString()], '  '))
        ->toThrow(ValidationException::class);
    expect($this->kegiatan->riwayatJadwal()->count())->toBe(0);
});

test('mentors cannot change fields outside the matrix (C-04)', function () {
    $offline = MetodeKegiatan::firstOrCreate(['nama' => 'Offline'], ['butuh_lokasi' => true]);

    app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan, [
        'tanggal' => now()->addDays(5)->toDateString(),
        'metode_id' => $offline->id,
        'status' => 'selesai',
        'sekolah_id' => 999,
    ], 'alasan');

    $kegiatan = $this->kegiatan->fresh();
    expect($kegiatan->metode_id)->toBe($this->kegiatan->metode_id)
        ->and($kegiatan->status)->toBe(StatusKegiatan::BelumDimulai)
        ->and($kegiatan->sekolah_id)->toBe($this->sekolah->id);
});

test('finished or cancelled activities cannot be rescheduled (BR-ACT-11)', function () {
    $this->kegiatan->update(['status' => StatusKegiatan::Selesai]);

    expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan->fresh(), ['tanggal' => now()->addDay()->toDateString()], 'alasan'))
        ->toThrow(AuthorizationException::class);
});

test('a stale version is rejected (E9)', function () {
    expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan, ['tanggal' => now()->addDays(2)->toDateString()], 'alasan', 99))
        ->toThrow(ValidationException::class);
});

test('online sessions need a meeting link', function () {
    expect(fn () => app(RescheduleKegiatan::class)->handle($this->mentor, $this->kegiatan, ['tautan_pertemuan' => null], 'alasan'))
        ->toThrow(ValidationException::class);
});

test('superadmin and admin cabang cannot write mentor reports (matrix)', function () {
    $sa = User::factory()->superadmin()->create();
    $ac = User::factory()->adminCabang($this->cabang)->create();

    expect(fn () => app(ReportKegiatan::class)->handle($sa, $this->kegiatan, StatusKegiatan::SedangBerlangsung, 'catatan'))->toThrow(AuthorizationException::class);
    expect(fn () => app(ReportKegiatan::class)->handle($ac, $this->kegiatan, StatusKegiatan::SedangBerlangsung, 'catatan'))->toThrow(AuthorizationException::class);
});

test('report needs a note and stores an append-only revision', function () {
    expect(fn () => app(ReportKegiatan::class)->handle($this->mentor, $this->kegiatan, StatusKegiatan::SedangBerlangsung, ' '))
        ->toThrow(ValidationException::class);

    app(ReportKegiatan::class)->handle($this->mentor, $this->kegiatan, StatusKegiatan::SedangBerlangsung, 'Mulai');
    app(ReportKegiatan::class)->handle($this->mentor, $this->kegiatan->fresh(), StatusKegiatan::Dibatalkan, 'Sekolah libur');

    expect(LaporanRevisi::where('kegiatan_id', $this->kegiatan->id)->orderBy('versi')->pluck('versi')->all())->toBe([1, 2])
        ->and($this->kegiatan->fresh()->status)->toBe(StatusKegiatan::Dibatalkan);
    expect(fn () => LaporanRevisi::first()->update(['catatan' => 'ubah']))->toThrow(LogicException::class);
});

test('a future activity cannot be marked done (BR-RPT-07)', function () {
    expect(fn () => app(ReportKegiatan::class)->handle($this->mentor, $this->kegiatan, StatusKegiatan::Selesai, 'Selesai'))
        ->toThrow(ValidationException::class);
});

test('finishing the initial training makes the school a partner and opens its certificate record', function () {
    Event::fake([StatusKegiatanDiperbarui::class]);
    $awal = Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::Awal, 'tanggal' => today()->subDay()]);

    app(ReportKegiatan::class)->handle($this->mentor, $awal, StatusKegiatan::Selesai, 'Pelatihan selesai');

    $sekolah = $this->sekolah->fresh();
    expect($sekolah->posisi_tahap)->toBe(PosisiTahap::MitraTl)
        ->and($sekolah->tanggal_mitra->toDateString())->toBe(today()->subDay()->toDateString())
        ->and(Sertifikat::where('sekolah_id', $sekolah->id)->where('tahap', 'awal')->whereNull('link')->exists())->toBeTrue();

    Event::assertDispatched(StatusKegiatanDiperbarui::class, fn ($e) => $e->progress->menjadiMitra);
});

test('reverting a finished initial training is refused once follow-up sessions exist (E8)', function () {
    $awal = Fixtures::kegiatan($this->sekolah, $this->mentor, ['tahap' => Tahap::Awal, 'tanggal' => today()->subDay()]);
    app(ReportKegiatan::class)->handle($this->mentor, $awal, StatusKegiatan::Selesai, 'Selesai');

    expect(fn () => app(ReportKegiatan::class)->handle($this->mentor, $awal->fresh(), StatusKegiatan::Dibatalkan, 'Salah input'))
        ->toThrow(ValidationException::class);
});
