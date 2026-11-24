<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Narahubung;
use App\Domain\Sekolah\Models\NarahubungRiwayat;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * AddNarahubung, UpdateNarahubung, DeactivateNarahubung and SetNarahubungUtama
 * (Implementation Plan 4.12). A school keeps exactly one active primary contact
 * (BR-SCH-06, AC-SCH-12); contacts are deactivated, never deleted, and every change
 * writes a history row (AC-SCH-16).
 */
final class ManageNarahubung
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 \-]{6,}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validate(array $input): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, Arr::only($input, Narahubung::FIELDS));

        return Validator::make($input, self::rules(), [
            'nomor_wa.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda hubung, dan awalan +.',
        ], ['nomor_wa' => 'nomor WhatsApp'])->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function add(User $actor, Sekolah $sekolah, array $input, bool $utama = false, ?int $usulanId = null): Narahubung
    {
        Gate::forUser($actor)->authorize($usulanId === null ? 'manageNarahubung' : 'decideNarahubung', $sekolah);
        $data = self::validate($input);

        return DB::transaction(function () use ($actor, $sekolah, $data, $utama, $usulanId) {
            Sekolah::query()->whereKey($sekolah->getKey())->lockForUpdate()->firstOrFail();

            if ($utama) {
                $this->clearUtama($actor, $sekolah, $usulanId);
            }

            $narahubung = Narahubung::create($data + ['sekolah_id' => $sekolah->id, 'is_utama' => $utama, 'aktif' => true]);
            $this->history($actor, $narahubung, null, $usulanId);
            AuditWriter::record('narahubung.create', $actor, $narahubung, null, null, $narahubung->snapshot());

            return $narahubung;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, Narahubung $narahubung, array $input, ?int $expectedVersion = null, ?int $usulanId = null): Narahubung
    {
        Gate::forUser($actor)->authorize($usulanId === null ? 'manageNarahubung' : 'decideNarahubung', $narahubung->sekolah);
        $data = self::validate($input + $narahubung->only(Narahubung::FIELDS));

        return DB::transaction(function () use ($actor, $narahubung, $data, $expectedVersion, $usulanId) {
            $locked = $this->lock($narahubung);
            OptimisticLock::check($locked, $expectedVersion);

            if (! $locked->aktif) {
                throw DomainRuleViolation::withMessage('Narahubung nonaktif tidak dapat diubah.');
            }

            $before = $locked->snapshot();
            $locked->fill($data);

            if (! $locked->isDirty()) {
                throw DomainRuleViolation::withMessage('Tidak ada perubahan data narahubung.');
            }

            OptimisticLock::bump($locked);
            $locked->save();
            $this->history($actor, $locked, $before, $usulanId);
            AuditWriter::record('narahubung.update', $actor, $locked, null, $before, $locked->snapshot());

            return $locked;
        });
    }

    public function deactivate(User $actor, Narahubung $narahubung): Narahubung
    {
        Gate::forUser($actor)->authorize('manageNarahubung', $narahubung->sekolah);

        return DB::transaction(function () use ($actor, $narahubung) {
            $locked = $this->lock($narahubung);

            if ($locked->is_utama) {
                throw DomainRuleViolation::withMessage('Narahubung utama tidak dapat dinonaktifkan. Tetapkan narahubung utama lain terlebih dahulu.');
            }

            if (! $locked->aktif) {
                throw DomainRuleViolation::withMessage('Narahubung sudah nonaktif.');
            }

            $before = $locked->snapshot();
            $locked->aktif = false;
            OptimisticLock::bump($locked);
            $locked->save();
            $this->history($actor, $locked, $before);
            AuditWriter::record('narahubung.deactivate', $actor, $locked, null, $before, $locked->snapshot());

            return $locked;
        });
    }

    public function setUtama(User $actor, Narahubung $narahubung): Narahubung
    {
        Gate::forUser($actor)->authorize('manageNarahubung', $narahubung->sekolah);

        return DB::transaction(function () use ($actor, $narahubung) {
            Sekolah::query()->whereKey($narahubung->sekolah_id)->lockForUpdate()->firstOrFail();
            $locked = $this->lock($narahubung);

            if (! $locked->aktif || $locked->is_utama) {
                throw DomainRuleViolation::withMessage($locked->is_utama ? 'Narahubung ini sudah menjadi narahubung utama.' : 'Narahubung nonaktif tidak dapat dijadikan utama.');
            }

            $this->clearUtama($actor, $locked->sekolah);

            $before = $locked->snapshot();
            $locked->is_utama = true;
            OptimisticLock::bump($locked);
            $locked->save();
            $this->history($actor, $locked, $before);
            AuditWriter::record('narahubung.set_utama', $actor, $locked, null, $before, $locked->snapshot());

            return $locked;
        });
    }

    private function clearUtama(User $actor, Sekolah $sekolah, ?int $usulanId = null): void
    {
        $current = Narahubung::query()->where('sekolah_id', $sekolah->id)->where('is_utama', true)->where('aktif', true)->lockForUpdate()->first();

        if ($current === null) {
            return;
        }

        $before = $current->snapshot();
        $current->is_utama = false;
        OptimisticLock::bump($current);
        $current->save();
        $this->history($actor, $current, $before, $usulanId);
    }

    private function lock(Narahubung $narahubung): Narahubung
    {
        /** @var Narahubung */
        return Narahubung::query()->whereKey($narahubung->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @param  array<string, mixed>|null  $before */
    private function history(User $actor, Narahubung $narahubung, ?array $before, ?int $usulanId = null): void
    {
        NarahubungRiwayat::create([
            'narahubung_id' => $narahubung->id,
            'data_sebelum' => $before,
            'data_sesudah' => $narahubung->snapshot(),
            'diubah_oleh' => $actor->id,
            'usulan_id' => $usulanId,
        ]);
    }
}
