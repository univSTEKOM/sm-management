<?php

namespace App\Domain\Shared\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminPusat = 'admin_pusat';
    case AdminCabang = 'admin_cabang';
    case Mentor = 'mentor';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminPusat => 'Admin Pusat',
            self::AdminCabang => 'Admin Cabang',
            self::Mentor => 'Mentor',
        };
    }

    public function isAdmin(): bool
    {
        return $this !== self::Mentor;
    }
}
