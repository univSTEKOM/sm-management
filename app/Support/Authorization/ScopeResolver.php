<?php

namespace App\Support\Authorization;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates AccessConfig scopes into query constraints and per-record checks.
 * Every read surface (Filament resources, selects, filters, widgets, custom
 * pages) and every domain action goes through this class.
 *
 * "Assigned" for a mentor means: active responsible mentor of the school, or
 * executing mentor of the activity (school-level checks accept executing
 * mentor of any non-cancelled activity of that school).
 */
final class ScopeResolver
{
    public static function applyToSekolahQuery(Builder $query, User $user, string $ability): void
    {
        if (! $user->aktif) {
            $query->whereRaw('1 = 0');

            return;
        }

        match (AccessConfig::scope($ability, $user->role)) {
            AccessConfig::ALL, AccessConfig::COORDINATION => null,
            AccessConfig::CABANG => $query->where($query->qualifyColumn('cabang_id'), $user->cabang_id),
            AccessConfig::RESPONSIBLE => $query->whereHas('penugasanAktif', fn (Builder $q) => $q->where('mentor_id', $user->id)),
            AccessConfig::ASSIGNED => $query->where(fn (Builder $q) => $q
                ->whereHas('penugasanAktif', fn (Builder $p) => $p->where('mentor_id', $user->id))
                ->orWhereHas('kegiatan', fn (Builder $k) => $k
                    ->where('mentor_pelaksana_id', $user->id)
                    ->where('status', '<>', StatusKegiatan::Dibatalkan->value))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function applyToKegiatanQuery(Builder $query, User $user, string $ability): void
    {
        if (! $user->aktif) {
            $query->whereRaw('1 = 0');

            return;
        }

        match (AccessConfig::scope($ability, $user->role)) {
            AccessConfig::ALL, AccessConfig::COORDINATION => null,
            AccessConfig::CABANG => $query->whereHas('sekolah', fn (Builder $q) => $q->where('cabang_id', $user->cabang_id)),
            AccessConfig::RESPONSIBLE => $query->whereHas('sekolah.penugasanAktif', fn (Builder $q) => $q->where('mentor_id', $user->id)),
            AccessConfig::ASSIGNED => $query->where(fn (Builder $q) => $q
                ->where($query->qualifyColumn('mentor_pelaksana_id'), $user->id)
                ->orWhereHas('sekolah.penugasanAktif', fn (Builder $p) => $p->where('mentor_id', $user->id))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Record-level check used by policies and domain actions. */
    public static function allows(User $user, string $ability, ?Sekolah $sekolah = null, ?Kegiatan $kegiatan = null): bool
    {
        if (! $user->aktif) {
            return false;
        }

        $sekolah ??= $kegiatan?->sekolah;

        return match (AccessConfig::scope($ability, $user->role)) {
            AccessConfig::ALL, AccessConfig::COORDINATION => true,
            AccessConfig::CABANG => $user->role === Role::AdminCabang
                && $sekolah !== null
                && (int) $sekolah->cabang_id === (int) $user->cabang_id,
            AccessConfig::RESPONSIBLE => $sekolah !== null && self::isResponsibleMentor($user, $sekolah),
            AccessConfig::ASSIGNED => $sekolah !== null && self::isAssignedMentor($user, $sekolah, $kegiatan),
            default => false,
        };
    }

    public static function isResponsibleMentor(User $user, Sekolah $sekolah): bool
    {
        return $sekolah->penugasan()
            ->whereNull('selesai')
            ->where('mentor_id', $user->id)
            ->exists();
    }

    public static function isAssignedMentor(User $user, Sekolah $sekolah, ?Kegiatan $kegiatan = null): bool
    {
        if (self::isResponsibleMentor($user, $sekolah)) {
            return true;
        }

        if ($kegiatan !== null) {
            return (int) $kegiatan->mentor_pelaksana_id === (int) $user->id;
        }

        return $sekolah->kegiatan()
            ->where('mentor_pelaksana_id', $user->id)
            ->where('status', '<>', StatusKegiatan::Dibatalkan->value)
            ->exists();
    }
}
