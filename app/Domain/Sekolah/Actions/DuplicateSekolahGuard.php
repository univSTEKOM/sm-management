<?php

namespace App\Domain\Sekolah\Actions;

use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Sekolah\Support\NamaSekolahNormalizer;
use App\Domain\Shared\Exceptions\DomainRuleViolation;

/** BR-SCH-03/13: duplicates are checked against all schools, including archived ones. */
final class DuplicateSekolahGuard
{
    public static function assertUnique(string $namaNormal, string $jenjang, int $kabupatenKotaId, ?int $ignoreId = null): void
    {
        $exists = Sekolah::query()
            ->where('nama_normal', $namaNormal)
            ->where('jenjang', $jenjang)
            ->where('kabupaten_kota_id', $kabupatenKotaId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw DomainRuleViolation::withMessage('Sekolah dengan nama, jenjang, dan kabupaten/kota yang sama sudah terdaftar.', 'nama');
        }
    }

    /**
     * Similar names for the warning in the form (AC-SCH-03). Uses pg_trgm.
     *
     * @return list<string>
     */
    public static function similarNames(string $nama, int $limit = 5): array
    {
        $normal = NamaSekolahNormalizer::normalize($nama);

        return Sekolah::query()
            ->whereRaw('similarity(nama_normal, ?) > 0.4', [$normal])
            ->orderByRaw('similarity(nama_normal, ?) desc', [$normal])
            ->limit($limit)
            ->pluck('nama')
            ->all();
    }
}
