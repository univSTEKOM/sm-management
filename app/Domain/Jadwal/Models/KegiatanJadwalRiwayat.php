<?php

namespace App\Domain\Jadwal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only schedule history (BR-ACT-06).
 *
 * @property int $id
 * @property int $kegiatan_id
 * @property int $versi
 * @property array<string, mixed> $sebelum
 * @property array<string, mixed> $sesudah
 * @property string $alasan
 * @property int $diubah_oleh
 * @property Carbon $created_at
 */
class KegiatanJadwalRiwayat extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'kegiatan_jadwal_riwayat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sebelum' => 'array', 'sesudah' => 'array'];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }
}
