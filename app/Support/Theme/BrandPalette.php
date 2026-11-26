<?php

namespace App\Support\Theme;

/**
 * Single source of the red visual identity. Panels read {@see primary()} and {@see gray()};
 * `resources/css/tema.css` mirrors the same values as CSS variables (a test keeps the two in sync).
 * Business status colours (success, warning, danger, info, gray) are Filament's own and are not
 * defined here, so statuses keep their meaning.
 */
final class BrandPalette
{
    public const PRIMARY = '#B91C1C';

    public const DARK = '#7F1D1D';

    public const LIGHT = '#FEE2E2';

    public const SOFT = '#FEF2F2';

    public const ACCENT = '#DC2626';

    public const BACKGROUND = '#F8FAFC';

    public const SURFACE = '#FFFFFF';

    public const TEXT = '#1E293B';

    public const TEXT_MUTED = '#64748B';

    public const BORDER = '#E2E8F0';

    /** Calendar: Pelatihan Awal is the brand red, Tindak Lanjut a deep blue (distinct for colour-blind users), cancelled is grey. */
    public const TAHAP_AWAL = self::PRIMARY;

    public const TAHAP_TINDAK_LANJUT = '#1E40AF';

    public const DIBATALKAN = self::TEXT_MUTED;

    /**
     * Shade scale for Filament's `primary`. Shade 600 (buttons, links) is the brand red;
     * 50/100 are the soft/light tints, 500 the accent and 800 the dark red.
     *
     * @return array<int, string>
     */
    public static function primary(): array
    {
        return [
            50 => self::SOFT,
            100 => self::LIGHT,
            200 => '#FECACA',
            300 => '#FCA5A5',
            400 => '#F87171',
            500 => self::ACCENT,
            600 => self::PRIMARY,
            700 => '#991B1B',
            800 => self::DARK,
            900 => '#5F1717',
            950 => '#3F0D0D',
        ];
    }

    /**
     * Neutral scale (Tailwind slate), which carries the page background, text and border tokens.
     *
     * @return array<int, string>
     */
    public static function gray(): array
    {
        return [
            50 => self::BACKGROUND,
            100 => '#F1F5F9',
            200 => self::BORDER,
            300 => '#CBD5E1',
            400 => '#94A3B8',
            500 => self::TEXT_MUTED,
            600 => '#475569',
            700 => '#334155',
            800 => self::TEXT,
            900 => '#0F172A',
            950 => '#020617',
        ];
    }
}
