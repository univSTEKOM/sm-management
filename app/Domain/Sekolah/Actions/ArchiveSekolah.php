<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Archives a school that withdrew (BR-SCH-05, AC-SCH-15). Schools are never deleted
 * (AC-SCH-11). Decision on K-09: archiving is refused while activities are still open;
 * they must be finished or cancelled (with a note) first.
 */
final class ArchiveSekolah
{
    public function handle(User $actor, Sekolah $sekolah, string $alasan): Sekolah
    {
        Gate::forUser($actor)->authorize('archive', $sekolah);

        $alasan = trim($alasan);

        if ($alasan === '') {
            throw DomainRuleViolation::withMessage('Alasan arsip wajib diisi.', 'alasan');
        }

        return DB::transaction(function () use ($actor, $sekolah, $alasan) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('archive', $locked);

            $terbuka = $locked->kegiatan()
                ->whereIn('status', [StatusKegiatan::BelumDimulai->value, StatusKegiatan::SedangBerlangsung->value])
                ->count();

            if ($terbuka > 0) {
                throw DomainRuleViolation::withMessage("Sekolah masih memiliki {$terbuka} kegiatan yang belum selesai. Selesaikan atau batalkan kegiatan tersebut (dengan catatan alasan) sebelum mengarsipkan.");
            }

            $locked->forceFill([
                'diarsipkan_at' => now(),
                'alasan_arsip' => $alasan,
                'diarsipkan_oleh' => $actor->id,
            ])->save();

            AuditWriter::record('sekolah.archive', $actor, $locked, null, ['diarsipkan' => false], ['diarsipkan' => true, 'alasan' => $alasan]);

            return $locked;
        });
    }
}
