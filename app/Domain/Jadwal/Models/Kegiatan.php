<?php

namespace App\Domain\Jadwal\Models;

use App\Domain\Master\Models\JenisKegiatan;
use App\Domain\Master\Models\MetodeKegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\Tahap;
use App\Models\User;
use App\Support\Authorization\ScopeResolver;
use Database\Factories\KegiatanFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Columns are written only by domain actions (App\Domain\Jadwal\Actions).
 */
/**
 * @property int $id
 * @property int $sekolah_id
 * @property Tahap $tahap
 * @property int $urutan
 * @property string $nama_sesi
 * @property int $metode_id
 * @property Carbon $tanggal
 * @property string $waktu_mulai
 * @property string $waktu_selesai
 * @property string|null $lokasi
 * @property string|null $tautan_pertemuan
 * @property int $mentor_pelaksana_id
 * @property StatusKegiatan $status
 * @property bool $is_sesi_terakhir
 * @property int $version
 * @property-read Sekolah $sekolah
 */
#[UseFactory(KegiatanFactory::class)]
class Kegiatan extends Model
{
    use HasFactory;

    protected $table = 'kegiatan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tahap' => Tahap::class,
            'status' => StatusKegiatan::class,
            'tanggal' => 'date',
            'is_sesi_terakhir' => 'boolean',
            'pelaksana_ditunjuk_khusus' => 'boolean',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class);
    }

    /** @return BelongsTo<MetodeKegiatan, $this> */
    public function metode(): BelongsTo
    {
        return $this->belongsTo(MetodeKegiatan::class, 'metode_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentorPelaksana(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_pelaksana_id');
    }

    /** @return HasMany<KegiatanJadwalRiwayat, $this> */
    public function riwayatJadwal(): HasMany
    {
        return $this->hasMany(KegiatanJadwalRiwayat::class)->orderBy('versi');
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user, string $ability = 'kegiatan.view'): void
    {
        ScopeResolver::applyToKegiatanQuery($query, $user, $ability);
    }
}
