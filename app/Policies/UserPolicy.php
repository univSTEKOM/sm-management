<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\AccessConfig;

/**
 * Account management is Superadmin only (BR-USR-01, AC-USR-01). Accounts are never
 * deleted (FR-USR-02). A Superadmin cannot deactivate, reset or change the role of
 * their own account, so the system is never locked out by accident (assumption F2-A03).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user) && $this->assignsRoles($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->manages($user);
    }

    public function assignRole(User $user, User $target): bool
    {
        return $this->assignsRoles($user) && ! $user->is($target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->manages($user) && ! $user->is($target) && $target->aktif;
    }

    public function activate(User $user, User $target): bool
    {
        return $this->manages($user) && ! $target->aktif;
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->manages($user) && ! $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    public function restore(User $user, User $target): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('users.manage', $user->role);
    }

    private function assignsRoles(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('roles.assign', $user->role);
    }
}
