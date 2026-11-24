<?php

namespace App\Domain\Sekolah\Support;

/**
 * Normalises school names for duplicate detection (BR-SCH-03): lower case,
 * punctuation removed, whitespace collapsed, standard abbreviations unified.
 */
final class NamaSekolahNormalizer
{
    /** Longest phrases first so they win over shorter ones. */
    private const ABBREVIATIONS = [
        'sekolah menengah kejuruan negeri' => 'smkn',
        'sekolah menengah atas negeri' => 'sman',
        'sekolah menengah kejuruan' => 'smk',
        'sekolah menengah atas' => 'sma',
        'madrasah aliyah negeri' => 'man',
        'madrasah aliyah' => 'ma',
        'smk negeri' => 'smkn',
        'sma negeri' => 'sman',
    ];

    public static function normalize(string $nama): string
    {
        $value = mb_strtolower($nama);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        foreach (self::ABBREVIATIONS as $phrase => $abbreviation) {
            $value = preg_replace('/\b'.preg_quote($phrase, '/').'\b/u', $abbreviation, $value) ?? $value;
        }

        return $value;
    }
}
