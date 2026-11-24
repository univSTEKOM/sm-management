<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Events\UsulanNarahubungDiajukan;
use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A mentor assigned to the school proposes a contact change or a new contact
 * (BR-SCH-11, AC-SCH-21). Data does not change until a decision is made.
 */
final class ProposeNarahubungChange
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Sekolah $sekolah, array $input, ?Narahubung $narahubung = null): NarahubungUsulan
    {
        Gate::forUser($actor)->authorize('proposeNarahubung', $sekolah);

        if ($narahubung !== null && ((int) $narahubung->sekolah_id !== (int) $sekolah->id || ! $narahubung->aktif)) {
            throw DomainRuleViolation::withMessage('Narahubung tidak ditemukan pada sekolah ini.');
        }

        $data = ManageNarahubung::validate($input + ($narahubung?->only(Narahubung::FIELDS) ?? []));

        if ($narahubung !== null && $data == $narahubung->only(Narahubung::FIELDS)) {
            throw DomainRuleViolation::withMessage('Usulan tidak mengubah data narahubung.');
        }

        $usulan = DB::transaction(function () use ($actor, $sekolah, $data, $narahubung) {
            $usulan = NarahubungUsulan::create([
                'sekolah_id' => $sekolah->id,
                'narahubung_id' => $narahubung?->id,
                'data_sebelum' => $narahubung?->snapshot(),
                'data_usulan' => $data,
                'diusulkan_oleh' => $actor->id,
                'diajukan_at' => now(),
                'status' => NarahubungUsulan::DIAJUKAN,
            ]);

            AuditWriter::record('narahubung.propose', $actor, $usulan, null, $usulan->data_sebelum, $data);

            return $usulan;
        });

        DB::afterCommit(fn () => UsulanNarahubungDiajukan::dispatch($usulan->id, $actor->id));

        return $usulan;
    }
}
