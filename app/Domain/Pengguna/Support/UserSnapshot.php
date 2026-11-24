<?php

namespace App\Domain\Pengguna\Support;

use App\Models\User;

/** Audit representation of an account; never contains password or tokens. */
final class UserSnapshot
{
    /** @return array<string, mixed> */
    public static function of(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'telepon' => $user->getAttribute('telepon'),
            'role' => $user->role->value,
            'cabang_id' => $user->cabang_id,
            'aktif' => $user->aktif,
        ];
    }
}
