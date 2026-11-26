<?php

namespace App\Domain\Shared\Enums;

enum StatusKegiatan: string
{
    case BelumDimulai = 'belum_dimulai';
    case SedangBerlangsung = 'sedang_berlangsung';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::BelumDimulai => 'Belum Dimulai',
            self::SedangBerlangsung => 'Sedang Berlangsung',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Badge colour (admin panel): the meaning of each status, never the brand colour. */
    public function color(): string
    {
        return match ($this) {
            self::BelumDimulai => 'gray',
            self::SedangBerlangsung => 'warning',
            self::Selesai => 'success',
            self::Dibatalkan => 'danger',
        };
    }

    /** Statuses that keep an activity "open" (not finished, not cancelled). */
    public function isOpen(): bool
    {
        return $this === self::BelumDimulai || $this === self::SedangBerlangsung;
    }
}
