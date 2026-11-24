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
}
