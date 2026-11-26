<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Records, replaces or revokes the MOU document of a school (owner decision D-03,
 * updated 26 Nov 2026: Superadmin and Admin Pusat may upload a PDF file).
 * "Sudah MOU" means a document file is recorded.
 */
final class RecordSekolahMou
{
    public function handle(User $actor, Sekolah $sekolah, string $dokumenPath, string $namaAsli, ?string $tanggal = null): Sekolah
    {
        Gate::forUser($actor)->authorize('recordMou', $sekolah);

        $data = Validator::make(
            ['dokumen_path' => trim($dokumenPath), 'nama_asli' => trim($namaAsli), 'tanggal' => $tanggal],
            [
                'dokumen_path' => ['required', 'string', 'max:2000'],
                'nama_asli' => ['required', 'string', 'max:255'],
                'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            [],
            ['dokumen_path' => 'path dokumen MOU', 'nama_asli' => 'nama file MOU', 'tanggal' => 'tanggal MOU'],
        )->validate();

        return DB::transaction(function () use ($actor, $sekolah, $data) {
            /** @var Sekolah $locked */
            $locked = Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('recordMou', $locked);
            $before = $this->snapshot($locked);

            // If replacing an existing document, delete the old file from storage
            if ($locked->mou_dokumen_path && $locked->mou_dokumen_path !== $data['dokumen_path']) {
                Storage::disk('local')->delete($locked->mou_dokumen_path);
            }

            $locked->forceFill([
                'mou_dokumen_path' => $data['dokumen_path'],
                'mou_dokumen_nama_asli' => $data['nama_asli'],
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

            // Delete the uploaded file from storage
            if ($locked->mou_dokumen_path) {
                Storage::disk('local')->delete($locked->mou_dokumen_path);
            }

            $locked->forceFill([
                'mou_dokumen_path' => null,
                'mou_dokumen_nama_asli' => null,
                'mou_tanggal' => null,
                'mou_dicatat_oleh' => $actor->id,
                'mou_dicatat_at' => now(),
            ])->save();

            AuditWriter::record('sekolah.mou_batal', $actor, $locked, null, $before, $this->snapshot($locked) + ['alasan' => trim($alasan)]);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Sekolah $sekolah): array
    {
        return [
            'mou_dokumen_path' => $sekolah->mou_dokumen_path,
            'mou_dokumen_nama_asli' => $sekolah->mou_dokumen_nama_asli,
            'mou_tanggal' => $sekolah->mou_tanggal?->toDateString(),
        ];
    }
}
