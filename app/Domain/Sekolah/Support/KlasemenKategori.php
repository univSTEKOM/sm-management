<?php

namespace App\Domain\Sekolah\Support;

use App\Domain\Master\Models\KategoriPelatihan;
use App\Models\User;
use App\Support\Authorization\AccessConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

/**
 * Klasemen kategori pelatihan (PRD 13.8, FR-RNK-01, BR-RNK-01..04): how many distinct partner
 * schools chose each category, all of Central Java, for every role (`klasemen.view` is ALL).
 *
 *  - one school counts once per category, however many sessions it has (BR-RNK-01/02);
 *  - only official partners count, i.e. `tanggal_mitra` is set (BR-RNK-04, AC-RNK-06);
 *  - archived partners still count (BR-RNK-04);
 *  - categories without schools are listed with 0 (AC-RNK-05);
 *  - descending by count, equal counts share a rank (dense ranking, AC-RNK-03).
 */
final class KlasemenKategori
{
    public static function bolehMelihat(User $user): bool
    {
        return $user->aktif && AccessConfig::allows('klasemen.view', $user->role);
    }

    /** @return Collection<int, array{id: int, nama: string, jumlah: int, peringkat: int}> */
    public static function handle(User $actor): Collection
    {
        if (! self::bolehMelihat($actor)) {
            throw new AuthorizationException('Tidak berwenang melihat klasemen.');
        }

        $mitra = fn ($query) => $query->whereNotNull('sekolah.tanggal_mitra');

        $rows = KategoriPelatihan::query()
            ->withCount(['sekolah as jumlah' => $mitra])
            // An inactive category stays in the ranking while partners still hold it.
            ->where(fn ($q) => $q->where('aktif', true)->orWhereHas('sekolah', $mitra))
            ->get()
            ->sortBy([['jumlah', 'desc'], ['nama', 'asc']])
            ->values();

        $hasil = collect();
        $peringkat = 0;
        $sebelumnya = null;

        foreach ($rows as $kategori) {
            $jumlah = (int) $kategori->getAttribute('jumlah');

            if ($jumlah !== $sebelumnya) {
                $peringkat++;
                $sebelumnya = $jumlah;
            }

            $hasil->push(['id' => $kategori->id, 'nama' => $kategori->nama, 'jumlah' => $jumlah, 'peringkat' => $peringkat]);
        }

        return $hasil;
    }
}
