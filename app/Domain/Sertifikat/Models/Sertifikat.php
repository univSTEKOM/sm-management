<?php

namespace App\Domain\Sertifikat\Models;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Domain\Shared\Enums\Tahap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per completed stage (FR-CRT-01). Status is derived: no row = Belum
 * Berlaku, empty link = Menunggu Penerbitan, link present = Tersedia.
 */
/**
 * @property int $id
 * @property Tahap $tahap
 * @property int $kegiatan_acuan_id
 * @property string|null $link
 */
class Sertifikat extends Model
{
    protected $table = 'sertifikat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tahap' => Tahap::class, 'tanggal_terbit' => 'date', 'diinput_at' => 'datetime'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function kegiatanAcuan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_acuan_id');
    }

    public function isTersedia(): bool
    {
        return filled($this->link);
    }
}
