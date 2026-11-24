<?php

namespace App\Domain\Sekolah\Models;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Models\User;
use App\Support\Authorization\ScopeResolver;
use Database\Factories\SekolahFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Columns are written only by domain actions (App\Domain\Sekolah\Actions).
 */
/**
 * @property int $id
 * @property string $nama
 * @property string $nama_normal
 * @property Jenjang $jenjang
 * @property int $cabang_id
 * @property int $kabupaten_kota_id
 * @property PosisiTahap $posisi_tahap
 * @property Carbon|null $tanggal_mitra
 * @property Carbon|null $tl_selesai_at
 * @property Carbon|null $diarsipkan_at
 * @property int $version
 */
#[UseFactory(SekolahFactory::class)]
class Sekolah extends Model
{
    use HasFactory;

    protected $table = 'sekolah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jenjang' => Jenjang::class,
            'posisi_tahap' => PosisiTahap::class,
            'tanggal_mitra' => 'date',
            'tl_selesai_at' => 'datetime',
            'diarsipkan_at' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kabupatenKota(): BelongsTo
    {
        return $this->belongsTo(KabupatenKota::class);
    }

    public function kategori(): BelongsToMany
    {
        return $this->belongsToMany(KategoriPelatihan::class, 'sekolah_kategori', 'sekolah_id', 'kategori_id');
    }

    public function narahubung(): HasMany
    {
        return $this->hasMany(Narahubung::class);
    }

    public function narahubungUtama(): HasOne
    {
        return $this->hasOne(Narahubung::class)->where('is_utama', true)->where('aktif', true);
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanMentor::class);
    }

    public function penugasanAktif(): HasOne
    {
        return $this->hasOne(PenugasanMentor::class)->whereNull('selesai');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    public function isArchived(): bool
    {
        return $this->diarsipkan_at !== null;
    }

    /** Rows the user may read (role, branch, assignment; PRD 27.3 A-01 for mentors). */
    #[Scope]
    protected function visibleTo(Builder $query, User $user, string $ability = 'sekolah.view'): void
    {
        ScopeResolver::applyToSekolahQuery($query, $user, $ability);
    }
}
