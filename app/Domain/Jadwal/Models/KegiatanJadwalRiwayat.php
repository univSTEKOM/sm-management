<?php

namespace App\Domain\Jadwal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only schedule history (BR-ACT-06). */
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
