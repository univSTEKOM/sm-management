<?php

namespace App\Domain\Shared\Enums;

/** Derived stage of a school (PRD 15.3). Never entered manually. */
enum PosisiTahap: string
{
    case CalonMitra = 'calon_mitra';
    case MitraBelumTl = 'mitra_belum_tl';
    case MitraTl = 'mitra_tl';
    case SelesaiProgram = 'selesai_program';

    public function label(): string
    {
        return match ($this) {
            self::CalonMitra => 'Calon Mitra',
            self::MitraBelumTl => 'Mitra – Belum Tindak Lanjut',
            self::MitraTl => 'Mitra – Tindak Lanjut',
            self::SelesaiProgram => 'Selesai Program',
        };
    }

    /** Informational badge: the stage is a position, not a good/bad state. */
    public function color(): string
    {
        return 'info';
    }
}
