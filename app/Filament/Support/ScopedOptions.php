<?php

namespace App\Filament\Support;

use App\Domain\Master\Models\Cabang;
use App\Domain\Shared\Enums\Role;
use App\Models\User;

/** Dropdown/filter options that respect the user's scope (risk F-R2). */
final class ScopedOptions
{
    /** @return array<int, string> */
    public static function cabang(User $user): array
    {
        return Cabang::query()
            ->where('aktif', true)
            ->when($user->role === Role::AdminCabang, fn ($q) => $q->whereKey($user->cabang_id))
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }
}
