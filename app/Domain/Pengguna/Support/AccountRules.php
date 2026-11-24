<?php

namespace App\Domain\Pengguna\Support;

use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Shared validation and guards for account actions (PRD 13.11, 16.4). */
final class AccountRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function profile(?User $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', self::uniqueEmail($ignore)],
            'telepon' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * cabang_id is required for Admin Cabang and optional (information only) for mentors (PRD 16.4).
     *
     * @return array<string, list<mixed>>
     */
    public static function role(?User $current = null): array
    {
        $keepCabang = $current?->cabang_id;

        return [
            'role' => ['required', Rule::enum(Role::class)],
            'cabang_id' => [
                'nullable',
                'integer',
                'required_if:role,'.Role::AdminCabang->value,
                // An inactive branch is not offered for new assignments, but an existing one may stay.
                Rule::exists('cabang', 'id')->where(fn ($q) => $q->where('aktif', true)->when($keepCabang, fn ($q) => $q->orWhere('id', $keepCabang))),
            ],
        ];
    }

    /** @return list<mixed> */
    public static function password(): array
    {
        return ['required', 'string', Password::min(8)];
    }

    /** Only Admin Cabang and mentors carry a branch. */
    public static function cabangFor(Role $role, mixed $cabangId): ?int
    {
        return in_array($role, [Role::AdminCabang, Role::Mentor], true) && filled($cabangId) ? (int) $cabangId : null;
    }

    /** Never leave the system without an active Superadmin. */
    public static function assertNotLastSuperadmin(User $target): void
    {
        if ($target->role !== Role::Superadmin || ! $target->aktif) {
            return;
        }

        $others = User::query()
            ->where('role', Role::Superadmin->value)
            ->where('aktif', true)
            ->whereKeyNot($target->getKey())
            ->exists();

        if (! $others) {
            throw DomainRuleViolation::withMessage('Harus tetap ada minimal satu Superadmin aktif.');
        }
    }

    /** BR-MNT-04 / PRD E4: a mentor with active assignments must be reassigned first. */
    public static function assertNoActiveAssignment(User $target, string $operation): void
    {
        if ($target->role !== Role::Mentor) {
            return;
        }

        $sekolah = PenugasanMentor::query()
            ->where('mentor_id', $target->getKey())
            ->whereNull('selesai')
            ->with('sekolah:id,nama')
            ->get()
            ->map(fn (PenugasanMentor $p) => (string) $p->sekolah->getAttribute('nama'))
            ->sort()
            ->values();

        if ($sekolah->isNotEmpty()) {
            throw DomainRuleViolation::withMessage(sprintf(
                'Mentor masih menjadi penanggung jawab aktif untuk %d sekolah (%s). Tugaskan mentor lain terlebih dahulu sebelum %s.',
                $sekolah->count(),
                $sekolah->take(5)->implode(', ').($sekolah->count() > 5 ? ', …' : ''),
                $operation,
            ));
        }
    }

    private static function uniqueEmail(?User $ignore): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $exists = User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $value))])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($exists) {
                $fail('Email sudah dipakai akun lain.');
            }
        };
    }
}
