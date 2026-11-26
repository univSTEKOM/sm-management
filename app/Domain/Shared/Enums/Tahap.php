<?php

namespace App\Domain\Shared\Enums;

enum Tahap: string
{
    case Awal = 'awal';
    case TindakLanjut = 'tindak_lanjut';

    public function label(): string
    {
        return match ($this) {
            self::Awal => 'Pelatihan Awal',
            self::TindakLanjut => 'Tindak Lanjut',
        };
    }

    /** Badge colour, aligned with the calendar: Awal is the brand red, Tindak Lanjut blue. */
    public function color(): string
    {
        return match ($this) {
            self::Awal => 'primary',
            self::TindakLanjut => 'info',
        };
    }
}
