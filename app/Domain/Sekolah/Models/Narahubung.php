<?php

namespace App\Domain\Sekolah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Columns are written only by the narahubung domain actions; every change leaves a
 * NarahubungRiwayat row (BR-SCH-06).
 *
 * @property int $id
 * @property int $sekolah_id
 * @property string $nama
 * @property string|null $jabatan
 * @property string $nomor_wa
 * @property string|null $email
 * @property bool $is_utama
 * @property bool $aktif
 * @property int $version
 */
class Narahubung extends Model
{
    public const FIELDS = ['nama', 'jabatan', 'nomor_wa', 'email'];

    protected $table = 'narahubung';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_utama' => 'boolean', 'aktif' => 'boolean'];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /** @return HasMany<NarahubungRiwayat, $this> */
    public function riwayat(): HasMany
    {
        return $this->hasMany(NarahubungRiwayat::class)->orderByDesc('id');
    }

    /** wa.me link; local numbers starting with 0 become +62. */
    public function waUrl(): string
    {
        $digits = preg_replace('/\D+/', '', $this->nomor_wa) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->only([...self::FIELDS, 'is_utama', 'aktif']);
    }
}
