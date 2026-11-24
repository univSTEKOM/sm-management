<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authorization\AccessConfig;

/** Halaman Setting is part of master data management (`master.manage`, Superadmin). */
class AppSettingPolicy
{
    public function manageSettings(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('master.manage', $user->role);
    }
}
