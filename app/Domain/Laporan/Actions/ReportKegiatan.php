<?php

namespace App\Domain\Laporan\Actions;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Laporan\Events\StatusKegiatanDiperbarui;
use App\Domain\Laporan\Models\LaporanRevisi;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Progress\ProgressRecalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mentor report: status + mandatory note, stored as a new revision
 * (BR-ACT-04, BR-RPT-01..05/07, AC-RPT-01..05/12). Valid immediately, no
 * admin verification. Admin Pusat status changes use the same path with a
 * mandatory reason (PD-05) through AdminChangeStatus.
 */
final class ReportKegiatan
{
    public function handle(User $actor, Kegiatan $kegiatan, StatusKegiatan $status, string $catatan, ?int $expectedVersion = null): Kegiatan
    {
        Gate::forUser($actor)->authorize('report', $kegiatan);

        return $this->apply($actor, $kegiatan, $status, $catatan, $expectedVersion, 'mentor', null);
    }

    /**
     * Shared write path for mentor reports and Admin Pusat corrections.
     *
     * @internal Called by AdminChangeStatus after its own authorization.
     */
    public function apply(
        User $actor,
        Kegiatan $kegiatan,
        StatusKegiatan $status,
        string $catatan,
        ?int $expectedVersion,
        string $peran,
        ?string $alasanAdmin,
    ): Kegiatan {
        if (trim($catatan) === '') {
            throw DomainRuleViolation::withMessage('Catatan wajib diisi.', 'catatan');
        }

        [$updated, $progress] = DB::transaction(function () use ($actor, $kegiatan, $status, $catatan, $expectedVersion, $peran, $alasanAdmin) {
            $sekolah = Sekolah::query()->whereKey($kegiatan->sekolah_id)->lockForUpdate()->firstOrFail();

            /** @var Kegiatan $locked */
            $locked = Kegiatan::query()->whereKey($kegiatan->getKey())->lockForUpdate()->firstOrFail();
            OptimisticLock::check($locked, $expectedVersion);

            $this->assertAllowedTransition($locked, $status, $sekolah);

            $before = ['status' => $locked->status->value];

            $locked->status = $status;
            OptimisticLock::bump($locked);
            $locked->save();

            LaporanRevisi::create([
                'kegiatan_id' => $locked->id,
                'versi' => (int) LaporanRevisi::where('kegiatan_id', $locked->id)->max('versi') + 1,
                'status' => $status->value,
                'catatan' => trim($catatan),
                'ditulis_oleh' => $actor->id,
                'peran_penulis' => $peran,
                'alasan_perubahan_admin' => $alasanAdmin === null ? null : trim($alasanAdmin),
            ]);

            $progress = ProgressRecalculator::forSekolah($sekolah);

            AuditWriter::record('kegiatan.status', $actor, $locked, null, $before, [
                'status' => $status->value,
                'catatan' => trim($catatan),
                'peran' => $peran,
                'alasan_admin' => $alasanAdmin,
            ]);

            return [$locked->fresh(), $progress];
        });

        StatusKegiatanDiperbarui::dispatch($updated, $actor, $progress);

        return $updated;
    }

    private function assertAllowedTransition(Kegiatan $kegiatan, StatusKegiatan $status, Sekolah $sekolah): void
    {
        if ($sekolah->isArchived()) {
            throw DomainRuleViolation::withMessage('Sekolah sudah diarsipkan.', 'status');
        }

        // BR-RPT-07: an activity dated in the future cannot be marked done.
        if ($status === StatusKegiatan::Selesai && $kegiatan->tanggal->isAfter(today())) {
            throw DomainRuleViolation::withMessage('Kegiatan bertanggal di masa depan tidak dapat ditandai Selesai. Jadwalkan ulang terlebih dahulu.', 'status');
        }

        if ($kegiatan->tahap === Tahap::Awal && $kegiatan->status === StatusKegiatan::Selesai && $status !== StatusKegiatan::Selesai) {
            // BR-AWL-04: locked once the certificate is available.
            $sertifikatTersedia = Sertifikat::query()
                ->where('sekolah_id', $sekolah->id)
                ->where('tahap', Tahap::Awal->value)
                ->whereNotNull('link')
                ->exists();

            // E8: reverting is allowed only before any follow-up session exists.
            $adaSesiTl = $sekolah->kegiatan()->where('tahap', Tahap::TindakLanjut->value)->exists();

            if ($sertifikatTersedia || $adaSesiTl) {
                throw DomainRuleViolation::withMessage('Status Pelatihan Awal yang sudah Selesai tidak dapat diubah karena sertifikat sudah tersedia atau sesi Tindak Lanjut sudah ada.', 'status');
            }
        }
    }
}
