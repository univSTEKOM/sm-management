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

    /** Owner decision D-03 (updated 26 Nov 2026): MOU is recorded or revoked by Superadmin and Admin Pusat only. */
    public function recordMou(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'sekolah.mou', $sekolah);
    }

    /** Superadmin and Admin Pusat may view/download the uploaded MOU document (including archived schools). */
    public function viewMou(User $user, Sekolah $sekolah): bool
    {
        return ScopeResolver::allows($user, 'sekolah.mou', $sekolah);
    }

    /** FR-JDA-01: schedule the initial training (awal.create), school not archived. */
    public function createAwal(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'awal.create', $sekolah);
    }

    /** FR-JTL-01 / BR-ACT-12: admins in scope and the responsible mentor add follow-up sessions. */
    public function createTl(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'tl.create', $sekolah);
    }

    /** BR-TL-10: once the last session is set, only Admin Pusat adds sessions. */
    public function createTlAfterLast(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'tl.create_after_last', $sekolah);
    }

    /** Designating an executing mentor other than the responsible one (kegiatan.assign_pelaksana). */
    public function assignPelaksanaBaru(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'kegiatan.assign_pelaksana', $sekolah);
    }

    public function manageNarahubung(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'narahubung.manage', $sekolah);
    }

    public function proposeNarahubung(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'narahubung.propose', $sekolah);
    }

    public function decideNarahubung(User $user, Sekolah $sekolah): bool
    {
        return ScopeResolver::allows($user, 'narahubung.decide', $sekolah);
    }

    public function assignMentor(User $user, Sekolah $sekolah): bool
    {
        return ! $sekolah->isArchived() && ScopeResolver::allows($user, 'penugasan.assign', $sekolah);
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
