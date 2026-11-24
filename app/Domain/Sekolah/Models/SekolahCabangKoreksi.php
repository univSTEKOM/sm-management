<?php

namespace App\Domain\Sekolah\Models;

use App\Domain\Master\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only record of a branch correction by Superadmin (BR-SCH-12). */
class SekolahCabangKoreksi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sekolah_cabang_koreksi';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('sekolah_cabang_koreksi is append-only.'));
        static::deleting(fn () => throw new LogicException('sekolah_cabang_koreksi is append-only.'));
    }

    /** @return BelongsTo<Cabang, $this> */
    public function cabangLama(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_lama_id');
    }

    /** @return BelongsTo<Cabang, $this> */
    public function cabangBaru(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_baru_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengoreksi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
