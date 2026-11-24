<?php

namespace App\Domain\Shared\Enums;

/** PRD 15.4 (derived). "Belum Berlaku" is shown to users as "Belum Tersedia" (UI brief 23 Nov 2026). */
enum StatusSertifikat: string
{
    case BelumBerlaku = 'belum_berlaku';
    case MenungguPenerbitan = 'menunggu_penerbitan';
    case Tersedia = 'tersedia';

    public function label(): string
    {
        return match ($this) {
            self::BelumBerlaku => 'Belum Tersedia',
            self::MenungguPenerbitan => 'Menunggu Penerbitan',
            self::Tersedia => 'Tersedia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BelumBerlaku => 'gray',
            self::MenungguPenerbitan => 'warning',
            self::Tersedia => 'success',
        };
    }
}
