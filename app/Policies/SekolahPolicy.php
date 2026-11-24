<?php

namespace App\Policies;

use App\Domain\Sekolah\Models\Sekolah;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use App\Support\Authorization\ScopeResolver;

/** Abilities come from AccessConfig (static matrix, TD-08). Deny by default. */
class SekolahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('sekolah.view', $user->role);
    }

    public function view(User $user, Sekolah $sekolah): bool
    {
        return ScopeResolver::allows($user, 'sekolah.view', $sekolah);
    }

    public function create(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('sekolah.create', $user->role);
    }

    public function update(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'sekolah.update', $sekolah);
    }

    public function updateKategori(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'sekolah.kategori', $sekolah);
    }

    public function archive(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'sekolah.archive', $sekolah);
    }

    public function correctCabang(User $user, Sekolah $sekolah): bool
    {
        return ScopeResolver::allows($user, 'sekolah.correct_cabang', $sekolah)
            && ! $sekolah->kegiatan()->exists();
    }

    public function delete(User $user, Sekolah $sekolah): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Sekolah $sekolah): bool
    {
        return false;
    }

    public function restore(User $user, Sekolah $sekolah): bool
    {
        return false;
    }
}
