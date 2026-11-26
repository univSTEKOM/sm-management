<?php

namespace App\Domain\Jadwal\Support;

use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Validation shared by the actions that create or change an activity (BR-ACT-10, AC-JDA-05). */
final class KegiatanRules
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function validate(array $input, array $extra = []): array
    {
        return Validator::make($input, [
            'tanggal' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'jenis_kegiatan_id' => ['required', 'integer', Rule::exists('jenis_kegiatan', 'id')->where('aktif', true)],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'mentor_pelaksana_id' => ['nullable', 'integer'],
        ] + $extra, [
            'waktu_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
            'required' => ':attribute wajib diisi.',
        ], [
            'tanggal' => 'Tanggal',
            'waktu_mulai' => 'Waktu mulai',
            'waktu_selesai' => 'Waktu selesai',
            'jenis_kegiatan_id' => 'Jenis kegiatan',
            'metode_id' => 'Metode',
            'nama_sesi' => 'Nama sesi',
        ])->validate();
    }

    /** BR-ACT-10: offline needs a location, online needs a meeting link. */
    public static function assertMethodFields(MetodeKegiatan $metode, ?string $lokasi, ?string $tautan): void
    {
        if ($metode->butuh_lokasi && blank($lokasi)) {
            throw DomainRuleViolation::withMessage('Lokasi wajib diisi untuk kegiatan offline.', 'lokasi');
        }

        if ($metode->butuh_tautan && blank($tautan)) {
            throw DomainRuleViolation::withMessage('Tautan pertemuan wajib diisi untuk kegiatan online.', 'tautan_pertemuan');
        }
    }

    public static function activeMentor(int $id): User
    {
        $mentor = User::query()->whereKey($id)->where('role', 'mentor')->where('aktif', true)->first();

        if ($mentor === null) {
            throw DomainRuleViolation::withMessage('Pilih mentor pelaksana yang aktif.', 'mentor_pelaksana_id');
        }

        return $mentor;
    }

    /** Executor is the responsible mentor unless an admin designates another (PRD 13.4 input). */
    public static function resolveExecutor(User $actor, Sekolah $sekolah, ?int $requestedId): array
    {
        $penanggungJawab = $sekolah->penugasanAktif()->first()?->mentor_id;

        if ($penanggungJawab === null) {
            throw DomainRuleViolation::withMessage('Sekolah belum memiliki mentor penanggung jawab.', 'mentor_pelaksana_id');
        }

        if ($requestedId === null || $requestedId === (int) $penanggungJawab) {
            return [(int) $penanggungJawab, false];
        }

        Gate::forUser($actor)->authorize('assignPelaksanaBaru', $sekolah);
        self::activeMentor($requestedId);

        return [$requestedId, true];
    }
}
