<?php

namespace App\Domain\Jadwal\Actions;

use App\Domain\Jadwal\Events\JadwalDiubah;
use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Jadwal\Models\KegiatanJadwalRiwayat;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\Tahap;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Changes the schedule of an activity (BR-ACT-05..08, BR-ACT-11, AC-JTL-08).
 *
 * Field allow-list per role (C-04, conservative): mentors may change only the
 * date, time, location and meeting link listed in the permission matrix;
 * admins may additionally change the delivery method.
 */
final class RescheduleKegiatan
{
    private const MENTOR_FIELDS = ['tanggal', 'waktu_mulai', 'waktu_selesai', 'lokasi', 'tautan_pertemuan'];

    private const ADMIN_FIELDS = [...self::MENTOR_FIELDS, 'metode_id'];

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Kegiatan $kegiatan, array $input, string $alasan, ?int $expectedVersion = null): Kegiatan
    {
        Gate::forUser($actor)->authorize('reschedule', $kegiatan);

        $allowed = $actor->role === Role::Mentor ? self::MENTOR_FIELDS : self::ADMIN_FIELDS;
        $changes = Arr::only($input, $allowed);

        if (trim($alasan) === '') {
            throw DomainRuleViolation::withMessage('Alasan perubahan jadwal wajib diisi.', 'alasan');
        }

        Validator::make($changes, [
            'tanggal' => ['sometimes', 'date'],
            'waktu_mulai' => ['sometimes', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'date_format:H:i'],
            'lokasi' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tautan_pertemuan' => ['sometimes', 'nullable', 'url:http,https', 'max:1000'],
            'metode_id' => ['sometimes', 'integer', Rule::exists('metode_kegiatan', 'id')
                ->where(fn ($q) => $q->where('aktif', true)->orWhere('id', $kegiatan->metode_id))],
        ])->validate();

        $updated = DB::transaction(function () use ($actor, $kegiatan, $changes, $alasan, $expectedVersion) {
            /** @var Kegiatan $locked */
            $locked = Kegiatan::query()->whereKey($kegiatan->getKey())->lockForUpdate()->firstOrFail();

            OptimisticLock::check($locked, $expectedVersion);

            // The activity may have changed between authorization and lock.
            Gate::forUser($actor)->authorize('reschedule', $locked);

            if ($locked->tahap === Tahap::Awal && array_key_exists('metode_id', $changes)) {
                // FR-JDA-03: initial training is always offline at the central campus.
                unset($changes['metode_id']);
            }

            $before = $this->snapshot($locked);
            $locked->fill($changes);
            $this->assertConsistent($locked);

            if ($this->snapshot($locked) === $before) {
                throw DomainRuleViolation::withMessage('Tidak ada perubahan jadwal.');
            }

            OptimisticLock::bump($locked);
            $locked->save();

            $after = $this->snapshot($locked);

            KegiatanJadwalRiwayat::create([
                'kegiatan_id' => $locked->id,
                'versi' => (int) KegiatanJadwalRiwayat::where('kegiatan_id', $locked->id)->max('versi') + 1,
                'sebelum' => $before,
                'sesudah' => $after,
                'alasan' => trim($alasan),
                'diubah_oleh' => $actor->id,
            ]);

            AuditWriter::record('kegiatan.reschedule', $actor, $locked, null, $before, $after + ['alasan' => trim($alasan)]);

            return $locked;
        });

        JadwalDiubah::dispatch($updated, $actor);

        return $updated;
    }

    private function assertConsistent(Kegiatan $kegiatan): void
    {
        if (substr((string) $kegiatan->waktu_selesai, 0, 5) <= substr((string) $kegiatan->waktu_mulai, 0, 5)) {
            throw DomainRuleViolation::withMessage('Waktu selesai harus setelah waktu mulai.', 'waktu_selesai');
        }

        $metode = MetodeKegiatan::findOrFail($kegiatan->metode_id);

        if ($metode->butuh_lokasi && blank($kegiatan->lokasi)) {
            throw DomainRuleViolation::withMessage('Lokasi wajib diisi untuk kegiatan offline.', 'lokasi');
        }

        if ($metode->butuh_tautan && blank($kegiatan->tautan_pertemuan)) {
            throw DomainRuleViolation::withMessage('Tautan pertemuan wajib diisi untuk kegiatan online.', 'tautan_pertemuan');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Kegiatan $kegiatan): array
    {
        return [
            'tanggal' => $kegiatan->tanggal->toDateString(),
            'waktu_mulai' => substr((string) $kegiatan->waktu_mulai, 0, 5),
            'waktu_selesai' => substr((string) $kegiatan->waktu_selesai, 0, 5),
            'lokasi' => $kegiatan->lokasi,
            'tautan_pertemuan' => $kegiatan->tautan_pertemuan,
            'metode_id' => (int) $kegiatan->metode_id,
        ];
    }
}
