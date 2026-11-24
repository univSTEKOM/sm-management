<?php

namespace App\Domain\Mentor\Actions;

use App\Domain\Mentor\Events\MentorDitugaskan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Sets the single responsible mentor of a school (BR-MNT-01/05, A-02: SA and AP). A
 * replacement ends the previous assignment, so the history keeps mentor, start, end and
 * who assigned it (BR-MNT-03). Mentors may handle schools across branches (BR-MNT-02).
 */
final class AssignMentor
{
    public function handle(User $actor, Sekolah $sekolah, int $mentorId, ?string $alasan = null): PenugasanMentor
    {
        Gate::forUser($actor)->authorize('assignMentor', $sekolah);

        $mentor = User::query()->whereKey($mentorId)->where('role', Role::Mentor->value)->where('aktif', true)->first();

        if ($mentor === null) {
            throw DomainRuleViolation::withMessage('Pilih mentor yang aktif.', 'mentor_id');
        }

        $penugasan = DB::transaction(function () use ($actor, $sekolah, $mentor, $alasan) {
            Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            /** @var PenugasanMentor|null $aktif */
            $aktif = PenugasanMentor::query()->where('sekolah_id', $sekolah->id)->whereNull('selesai')->lockForUpdate()->first();

            if ($aktif !== null && (int) $aktif->mentor_id === $mentor->id) {
                throw DomainRuleViolation::withMessage('Mentor tersebut sudah menjadi penanggung jawab sekolah ini.', 'mentor_id');
            }

            $aktif?->forceFill(['selesai' => now(), 'alasan_selesai' => filled($alasan) ? trim((string) $alasan) : 'Diganti'])->save();

            $baru = PenugasanMentor::create([
                'sekolah_id' => $sekolah->id,
                'mentor_id' => $mentor->id,
                'mulai' => now(),
                'ditugaskan_oleh' => $actor->id,
            ]);

            AuditWriter::record('penugasan.assign', $actor, $baru, null,
                ['mentor_id' => $aktif?->mentor_id],
                ['mentor_id' => $mentor->id, 'sekolah_id' => $sekolah->id, 'alasan' => $alasan],
            );

            return $baru;
        });

        DB::afterCommit(fn () => MentorDitugaskan::dispatch($penugasan->id, $actor->id));

        return $penugasan;
    }
}
