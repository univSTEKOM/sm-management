<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Events\UsulanNarahubungDiputuskan;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Admin Pusat or the school's Admin Cabang approves or rejects a mentor's proposal
 * (BR-SCH-11, AC-SCH-22/23). Approval applies the change through ManageNarahubung, so the
 * history row carries the proposal id; rejection requires a reason and changes no data.
 */
final class DecideNarahubungProposal
{
    public function handle(User $actor, NarahubungUsulan $usulan, bool $setujui, ?string $alasanPenolakan = null): NarahubungUsulan
    {
        Gate::forUser($actor)->authorize('decideNarahubung', $usulan->sekolah);

        $alasanPenolakan = $alasanPenolakan === null ? null : trim($alasanPenolakan);

        if (! $setujui && ($alasanPenolakan === null || $alasanPenolakan === '')) {
            throw DomainRuleViolation::withMessage('Alasan penolakan wajib diisi.', 'alasan_penolakan');
        }

        $result = DB::transaction(function () use ($actor, $usulan, $setujui, $alasanPenolakan) {
            /** @var NarahubungUsulan $locked */
            $locked = NarahubungUsulan::query()->whereKey($usulan->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw DomainRuleViolation::withMessage('Usulan ini sudah diputuskan.');
            }

            if ($setujui) {
                $this->apply($actor, $locked);
            }

            $locked->forceFill([
                'status' => $setujui ? NarahubungUsulan::DISETUJUI : NarahubungUsulan::DITOLAK,
                'diputuskan_oleh' => $actor->id,
                'diputuskan_at' => now(),
                'alasan_penolakan' => $setujui ? null : $alasanPenolakan,
            ])->save();

            AuditWriter::record($setujui ? 'narahubung.proposal_approve' : 'narahubung.proposal_reject', $actor, $locked, null,
                ['status' => NarahubungUsulan::DIAJUKAN],
                ['status' => $locked->status, 'alasan_penolakan' => $locked->alasan_penolakan],
            );

            return $locked;
        });

        DB::afterCommit(fn () => UsulanNarahubungDiputuskan::dispatch($result->id, $actor->id));

        return $result;
    }

    private function apply(User $actor, NarahubungUsulan $usulan): void
    {
        $manage = app(ManageNarahubung::class);

        if ($usulan->narahubung_id === null) {
            $manage->add($actor, $usulan->sekolah, $usulan->data_usulan, false, $usulan->id);

            return;
        }

        /** @var Narahubung $narahubung */
        $narahubung = Narahubung::query()->findOrFail($usulan->narahubung_id);

        if (! $narahubung->aktif) {
            throw DomainRuleViolation::withMessage('Narahubung yang diusulkan untuk diubah sudah nonaktif. Tolak usulan ini.');
        }

        $manage->update($actor, $narahubung, $usulan->data_usulan, null, $usulan->id);
    }
}
