<?php

namespace App\Domain\Shared\Enums;

/**
 * Overall training status of a school (owner decision 23 Nov 2026, PHASE_LOG D-02).
 * Display only and always derived from activities; never stored or entered (BR-SCH-08).
 */
enum StatusPelatihan: string
{
    case BelumDimulai = 'belum_dimulai';
    case SedangBerlangsung = 'sedang_berlangsung';
    case SudahSelesai = 'sudah_selesai';

    public function label(): string
    {
        return match ($this) {
            self::BelumDimulai => 'Belum dimulai',
            self::SedangBerlangsung => 'Sedang berlangsung',
            self::SudahSelesai => 'Sudah selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BelumDimulai => 'gray',
            self::SedangBerlangsung => 'warning',
            self::SudahSelesai => 'success',
        };
    }

    /**
     * Belum dimulai: no activity is running or finished yet. Sedang berlangsung: at least one
     * activity is running or finished but the programme is not finished. Sudah selesai:
     * Selesai Program (PRD 15.3: last session set by AP and every non-cancelled session done).
     */
    public static function derive(PosisiTahap $posisi, int $kegiatanBerjalan): self
    {
        return match (true) {
            $posisi === PosisiTahap::SelesaiProgram => self::SudahSelesai,
            $kegiatanBerjalan > 0 => self::SedangBerlangsung,
            default => self::BelumDimulai,
        };
    }
}
