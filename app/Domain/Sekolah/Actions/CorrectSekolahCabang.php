<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Models\SekolahCabangKoreksi;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Superadmin corrects a wrongly entered branch, only while the school has no activity at
 * all, including cancelled ones (BR-SCH-12, AC-SCH-18/19). This is not a transfer (BR-SCH-10).
 */
final class CorrectSekolahCabang
{
    public function handle(User $actor, Sekolah $sekolah, int $cabangBaruId, string $alasan): Sekolah
    {
        Gate::forUser($actor)->authorize('correctCabang', $sekolah);

        $data = Validator::make(['cabang_id' => $cabangBaruId, 'alasan' => trim($alasan)], [
            'cabang_id' => ['required', 'integer', Rule::exists('cabang', 'id')->where('aktif', true), Rule::notIn([$sekolah->cabang_id])],
            'alasan' => ['required', 'string', 'max:2000'],
        ], ['cabang_id.not_in' => 'Pilih cabang yang berbeda dari cabang saat ini.'], ['cabang_id' => 'cabang baru'])->validate();

        return DB::transaction(function () use ($actor, $sekolah, $data) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->kegiatan()->exists()) {
                throw DomainRuleViolation::withMessage('Cabang tidak dapat dikoreksi karena sekolah sudah memiliki kegiatan.');
            }

            $lama = $locked->cabang_id;
            $locked->forceFill(['cabang_id' => $data['cabang_id']])->save();

            SekolahCabangKoreksi::create([
                'sekolah_id' => $locked->id,
                'cabang_lama_id' => $lama,
                'cabang_baru_id' => $data['cabang_id'],
                'alasan' => $data['alasan'],
                'oleh' => $actor->id,
            ]);

            AuditWriter::record('sekolah.correct_cabang', $actor, $locked, null, ['cabang_id' => $lama], ['cabang_id' => $data['cabang_id'], 'alasan' => $data['alasan']]);

            return $locked;
        });
    }
}
