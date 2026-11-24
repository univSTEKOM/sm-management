<?php

namespace App\Domain\Laporan\Models;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Shared\Enums\StatusKegiatan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only report revisions (BR-RPT-03). The latest version is the active report. */
class LaporanRevisi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'laporan_revisi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => StatusKegiatan::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Report revisions are append-only.'));
        static::deleting(fn () => throw new LogicException('Report revisions are append-only.'));
    }

    /** @return BelongsTo<Kegiatan, $this> */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditulis_oleh');
    }
}
