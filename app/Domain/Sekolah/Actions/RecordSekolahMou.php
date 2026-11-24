<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Records, replaces or revokes the MOU document of a school (owner decision D-03,
 * Admin Pusat only). "Sudah MOU" means a document link is recorded.
 */
final class RecordSekolahMou
{
    public function handle(User $actor, Sekolah $sekolah, string $dokumenUrl, ?string $tanggal = null): Sekolah
    {
        Gate::forUser($actor)->authorize('recordMou', $sekolah);

        $data = Validator::make(['dokumen_url' => trim($dokumenUrl), 'tanggal' => $tanggal], [
            'dokumen_url' => ['required', 'url:http,https', 'max:2000'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['dokumen_url' => 'tautan dokumen MOU', 'tanggal' => 'tanggal MOU'])->validate();

        return DB::transaction(function () use ($actor, $sekolah, $data) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('recordMou', $locked);
            $before = $this->snapshot($locked);

            $locked->forceFill([
                'mou_dokumen_url' => $data['dokumen_url'],
                'mou_tanggal' => $data['tanggal'] ?? null,
                'mou_dicatat_oleh' => $actor->id,
                'mou_dicatat_at' => now(),
            ])->save();

            AuditWriter::record('sekolah.mou', $actor, $locked, null, $before, $this->snapshot($locked));

            return $locked;
        });
    }

    public function revoke(User $actor, Sekolah $sekolah, string $alasan): Sekolah
    {
        Gate::forUser($actor)->authorize('recordMou', $sekolah);

        if (trim($alasan) === '') {
            throw DomainRuleViolation::withMessage('Alasan pembatalan MOU wajib diisi.', 'alasan');
        }

        return DB::transaction(function () use ($actor, $sekolah, $alasan) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isMou()) {
                throw DomainRuleViolation::withMessage('Sekolah ini belum tercatat MOU.');
            }

            $before = $this->snapshot($locked);
            $locked->forceFill(['mou_dokumen_url' => null, 'mou_tanggal' => null, 'mou_dicatat_oleh' => $actor->id, 'mou_dicatat_at' => now()])->save();

            AuditWriter::record('sekolah.mou_batal', $actor, $locked, null, $before, $this->snapshot($locked) + ['alasan' => trim($alasan)]);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Sekolah $sekolah): array
    {
        return [
            'mou_dokumen_url' => $sekolah->mou_dokumen_url,
            'mou_tanggal' => $sekolah->mou_tanggal?->toDateString(),
        ];
    }
}
