<?php

namespace App\Domain\Jadwal\Support;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use App\Support\Authorization\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read side of the schedule calendar (T-18). Events are the existing `kegiatan` rows,
 * loaded through the same scoped query as the Jadwal list (`kegiatan.view`). Filters can
 * only narrow what the scope already allows; an unknown filter value yields no rows.
 */
final class KalenderQuery
{
    /**
     * @param  array{tahap?: mixed, status?: mixed, cabang?: mixed, mentor?: mixed}  $filters
     * @return Builder<Kegiatan>
     */
    public static function build(User $user, string $from, string $to, array $filters = []): Builder
    {
        $query = Kegiatan::query()->with(['sekolah.cabang', 'metode', 'mentorPelaksana']);

        ScopeResolver::applyToKegiatanQuery($query, $user, 'kegiatan.view');

        $query->where('tanggal', '>=', Carbon::parse($from)->toDateString())
            ->where('tanggal', '<', Carbon::parse($to)->toDateString());

        if (filled($filters['tahap'] ?? null)) {
            self::narrow($query, 'tahap', Tahap::tryFrom((string) $filters['tahap'])?->value);
        }

        if (filled($filters['status'] ?? null)) {
            self::narrow($query, 'status', StatusKegiatan::tryFrom((string) $filters['status'])?->value);
        }

        if (filled($filters['mentor'] ?? null)) {
            self::narrow($query, 'mentor_pelaksana_id', ctype_digit((string) $filters['mentor']) ? (int) $filters['mentor'] : null);
        }

        if (filled($filters['cabang'] ?? null)) {
            $cabangId = ctype_digit((string) $filters['cabang']) ? (int) $filters['cabang'] : null;

            $cabangId === null
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('sekolah', fn (Builder $q) => $q->where('cabang_id', $cabangId));
        }

        return $query->orderBy('tanggal')->orderBy('waktu_mulai')->orderBy('id');
    }

    /**
     * @param  Builder<Kegiatan>  $query
     */
    private static function narrow(Builder $query, string $column, string|int|null $value): void
    {
        $value === null ? $query->whereRaw('1 = 0') : $query->where($column, $value);
    }
}
