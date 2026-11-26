<?php

namespace App\Domain\Jadwal\Actions;

use App\Domain\Jadwal\Events\PelaksanaDitunjuk;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Support\KegiatanRules;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** "Tunjuk Pelaksana": Superadmin or Admin Pusat picks the executing mentor of an open activity. */
final class AssignPelaksanaKegiatan
{
    public function handle(User $actor, Kegiatan $kegiatan, int $mentorId, ?int $expectedVersion = null): Kegiatan
    {
        Gate::forUser($actor)->authorize('assignPelaksana', $kegiatan);

        $mentor = KegiatanRules::activeMentor($mentorId);

        $locked = DB::transaction(function () use ($actor, $kegiatan, $mentor, $expectedVersion) {
            /** @var Kegiatan $locked */
            $locked = Kegiatan::query()->whereKey($kegiatan->getKey())->lockForUpdate()->firstOrFail();

            OptimisticLock::check($locked, $expectedVersion);
            Gate::forUser($actor)->authorize('assignPelaksana', $locked);

            if ((int) $locked->mentor_pelaksana_id === $mentor->id) {
                throw DomainRuleViolation::withMessage('Mentor tersebut sudah menjadi pelaksana kegiatan ini.', 'mentor_id');
            }

            $lama = $locked->mentor_pelaksana_id;
            $penanggungJawab = $locked->sekolah->penugasanAktif()->first()?->mentor_id;

            $locked->forceFill([
                'mentor_pelaksana_id' => $mentor->id,
                'pelaksana_ditunjuk_khusus' => $penanggungJawab === null || (int) $penanggungJawab !== $mentor->id,
            ]);
            OptimisticLock::bump($locked);
            $locked->save();

            AuditWriter::record('kegiatan.assign_pelaksana', $actor, $locked, null,
                ['mentor_pelaksana_id' => $lama], ['mentor_pelaksana_id' => $mentor->id]);

            return $locked;
        });

        DB::afterCommit(fn () => PelaksanaDitunjuk::dispatch($locked->id, $actor->id));

        return $locked;
    }
}
