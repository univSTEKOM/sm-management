<?php

namespace App\Domain\Jadwal\Actions;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Non-schedule details: session name (AC-JTL-05, number stays), activity type, notes.
 * Admins in scope only; schedule fields go through RescheduleKegiatan.
 */
final class UpdateKegiatanDetail
{
    /** @param  array<string, mixed>  $input  nama_sesi, jenis_kegiatan_id, keterangan */
    public function handle(User $actor, Kegiatan $kegiatan, array $input, ?int $expectedVersion = null): Kegiatan
    {
        Gate::forUser($actor)->authorize('updateDetail', $kegiatan);

        $data = Validator::make($input, [
            'nama_sesi' => ['sometimes', 'required', 'string', 'max:150'],
            'jenis_kegiatan_id' => ['sometimes', 'integer', Rule::exists('jenis_kegiatan', 'id')
                ->where(fn ($q) => $q->where('aktif', true)->orWhere('id', $kegiatan->jenis_kegiatan_id))],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], ['nama_sesi.required' => 'Nama sesi wajib diisi.'])->validate();

        return DB::transaction(function () use ($actor, $kegiatan, $data, $expectedVersion) {
            /** @var Kegiatan $locked */
            $locked = Kegiatan::query()->whereKey($kegiatan->getKey())->lockForUpdate()->firstOrFail();

            OptimisticLock::check($locked, $expectedVersion);
            Gate::forUser($actor)->authorize('updateDetail', $locked);

            // The initial training has a fixed name.
            if ($locked->tahap === Tahap::Awal) {
                unset($data['nama_sesi']);
            }

            $before = $locked->only(array_keys($data));
            $locked->fill(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data));

            if (! $locked->isDirty()) {
                throw DomainRuleViolation::withMessage('Tidak ada perubahan.');
            }

            OptimisticLock::bump($locked);
            $locked->save();

            AuditWriter::record('kegiatan.update_detail', $actor, $locked, null, $before, $locked->only(array_keys($data)));

            return $locked;
        });
    }
}
