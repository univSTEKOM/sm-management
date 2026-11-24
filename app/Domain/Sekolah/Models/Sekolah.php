<?php

namespace App\Domain\Sekolah\Models;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Laporan\Models\LaporanRevisi;
use App\Domain\Master\Models\Cabang;
use App\Domain\Master\Models\KabupatenKota;
use App\Domain\Master\Models\KategoriPelatihan;
use App\Domain\Mentor\Models\PenugasanMentor;
use App\Domain\Sertifikat\Models\Sertifikat;
use App\Domain\Shared\Enums\Jenjang;
use App\Domain\Shared\Enums\PosisiTahap;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Domain\Shared\Enums\StatusPelatihan;
use App\Domain\Shared\Enums\StatusSertifikat;
use App\Domain\Shared\Enums\Tahap;
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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
 * @property string|null $alasan_arsip
 * @property string|null $mou_dokumen_url
 * @property Carbon|null $mou_tanggal
 * @property int|null $mou_dicatat_oleh
 * @property Carbon|null $mou_dicatat_at
 * @property int $version
 * @property int|null $kegiatan_berjalan_count
 * @property int|null $tl_total_count
 * @property int|null $tl_selesai_count
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
            'mou_tanggal' => 'date',
            'mou_dicatat_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cabang, $this> */
    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    /** @return BelongsTo<KabupatenKota, $this> */
    public function kabupatenKota(): BelongsTo
    {
        return $this->belongsTo(KabupatenKota::class);
    }

    /** @return BelongsToMany<KategoriPelatihan, $this> */
    public function kategori(): BelongsToMany
    {
        return $this->belongsToMany(KategoriPelatihan::class, 'sekolah_kategori', 'sekolah_id', 'kategori_id');
    }

    /** @return HasMany<Narahubung, $this> */
    public function narahubung(): HasMany
    {
        return $this->hasMany(Narahubung::class);
    }

    /** @return HasMany<Narahubung, $this> */
    public function narahubungAktif(): HasMany
    {
        return $this->hasMany(Narahubung::class)->where('aktif', true)->orderByDesc('is_utama')->orderBy('nama');
    }

    /** @return HasMany<NarahubungUsulan, $this> */
    public function usulanNarahubung(): HasMany
    {
        return $this->hasMany(NarahubungUsulan::class);
    }

    /** @return HasOne<Narahubung, $this> */
    public function narahubungUtama(): HasOne
    {
        return $this->hasOne(Narahubung::class)->where('is_utama', true)->where('aktif', true);
    }

    /** @return HasMany<PenugasanMentor, $this> */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanMentor::class);
    }

    /** @return HasOne<PenugasanMentor, $this> */
    public function penugasanAktif(): HasOne
    {
        return $this->hasOne(PenugasanMentor::class)->whereNull('selesai');
    }

    /** @return HasMany<Kegiatan, $this> */
    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    /** @return HasOne<Kegiatan, $this> */
    public function kegiatanAwal(): HasOne
    {
        return $this->hasOne(Kegiatan::class)->where('tahap', Tahap::Awal->value)->where('status', '<>', StatusKegiatan::Dibatalkan->value);
    }

    /**
     * Report notes of every activity of the school, newest first (PRD 13.3 "Progres & Catatan").
     *
     * @return HasManyThrough<LaporanRevisi, Kegiatan, $this>
     */
    public function laporanRevisi(): HasManyThrough
    {
        return $this->hasManyThrough(LaporanRevisi::class, Kegiatan::class)->latest('laporan_revisi.id');
    }

    /** @return HasMany<SekolahCabangKoreksi, $this> */
    public function koreksiCabang(): HasMany
    {
        return $this->hasMany(SekolahCabangKoreksi::class)->latest('id');
    }

    /** @return HasManyThrough<NarahubungRiwayat, Narahubung, $this> */
    public function riwayatNarahubung(): HasManyThrough
    {
        return $this->hasManyThrough(NarahubungRiwayat::class, Narahubung::class)->latest('narahubung_riwayat.id');
    }

    /** @return HasMany<Sertifikat, $this> */
    public function sertifikat(): HasMany
    {
        return $this->hasMany(Sertifikat::class);
    }

    public function isMou(): bool
    {
        return filled($this->mou_dokumen_url);
    }

    /**
     * Counts used by list pages without N+1 queries (statusPelatihan, progres TL, PRD 15.5).
     *
     * @param  Builder<Sekolah>  $query
     */
    #[Scope]
    protected function withRingkasan(Builder $query): void
    {
        $query->withCount(self::ringkasanCounts());
    }

    /** @return array<string, \Closure> */
    public static function ringkasanCounts(): array
    {
        return [
            'kegiatan as kegiatan_berjalan_count' => fn ($q) => $q->whereIn('status', [StatusKegiatan::SedangBerlangsung->value, StatusKegiatan::Selesai->value]),
            'kegiatan as tl_total_count' => fn ($q) => $q->where('tahap', Tahap::TindakLanjut->value)->where('status', '<>', StatusKegiatan::Dibatalkan->value),
            'kegiatan as tl_selesai_count' => fn ($q) => $q->where('tahap', Tahap::TindakLanjut->value)->where('status', StatusKegiatan::Selesai->value),
        ];
    }

    /**
     * @param  Builder<Sekolah>  $query
     */
    #[Scope]
    protected function whereStatusPelatihan(Builder $query, StatusPelatihan $status): void
    {
        $berjalan = fn ($q) => $q->whereIn('status', [StatusKegiatan::SedangBerlangsung->value, StatusKegiatan::Selesai->value]);

        match ($status) {
            StatusPelatihan::SudahSelesai => $query->where('posisi_tahap', PosisiTahap::SelesaiProgram->value),
            StatusPelatihan::SedangBerlangsung => $query->where('posisi_tahap', '<>', PosisiTahap::SelesaiProgram->value)->whereHas('kegiatan', $berjalan),
            StatusPelatihan::BelumDimulai => $query->where('posisi_tahap', '<>', PosisiTahap::SelesaiProgram->value)->whereDoesntHave('kegiatan', $berjalan),
        };
    }

    public function statusPelatihan(): StatusPelatihan
    {
        $berjalan = $this->kegiatan_berjalan_count
            ?? $this->kegiatan()->whereIn('status', [StatusKegiatan::SedangBerlangsung->value, StatusKegiatan::Selesai->value])->count();

        return StatusPelatihan::derive($this->posisi_tahap, $berjalan);
    }

    /** PRD 15.5: "x/y sesi selesai" (y excludes cancelled sessions). Null when no session exists. */
    public function progresTl(): ?string
    {
        $total = $this->tl_total_count ?? $this->kegiatan()->where('tahap', Tahap::TindakLanjut->value)->where('status', '<>', StatusKegiatan::Dibatalkan->value)->count();

        if ($total === 0) {
            return null;
        }

        $selesai = $this->tl_selesai_count ?? $this->kegiatan()->where('tahap', Tahap::TindakLanjut->value)->where('status', StatusKegiatan::Selesai->value)->count();

        return "{$selesai}/{$total} sesi selesai";
    }

    /** PRD 15.4. Uses the eager-loaded `sertifikat` relation when present. */
    public function statusSertifikat(Tahap $tahap): StatusSertifikat
    {
        $sertifikat = $this->sertifikat->firstWhere('tahap', $tahap);

        return match (true) {
            $sertifikat === null => StatusSertifikat::BelumBerlaku,
            $sertifikat->isTersedia() => StatusSertifikat::Tersedia,
            default => StatusSertifikat::MenungguPenerbitan,
        };
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
