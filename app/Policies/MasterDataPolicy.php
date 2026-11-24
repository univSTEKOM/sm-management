<?php

namespace App\Policies;

use App\Domain\Master\Support\MasterRegistry;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * All master data types (BR-USR-02: Superadmin only). Deletion is limited to records
 * that are not referenced anywhere (FR-MST-02); used records are deactivated instead.
 */
class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->manages($user) && ! MasterRegistry::isUsed($record);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('master.manage', $user->role);
    }
}
