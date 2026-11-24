<?php

namespace App\Policies;

use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use App\Support\Authorization\ScopeResolver;

/** Proposals are reviewed by whoever may decide them (narahubung.decide: AP all, AC branch). */
class NarahubungUsulanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->aktif && AccessConfig::scope('narahubung.decide', $user->role) !== AccessConfig::NONE;
    }

    public function view(User $user, NarahubungUsulan $usulan): bool
    {
        return ScopeResolver::allows($user, 'narahubung.decide', $usulan->sekolah);
    }

    public function decide(User $user, NarahubungUsulan $usulan): bool
    {
        return $usulan->isOpen() && ScopeResolver::allows($user, 'narahubung.decide', $usulan->sekolah);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NarahubungUsulan $usulan): bool
    {
        return false;
    }

    public function delete(User $user, NarahubungUsulan $usulan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
