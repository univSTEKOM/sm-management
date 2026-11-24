<?php

namespace App\Domain\Sekolah\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only history of a contact person (BR-SCH-06, AC-SCH-16). */
class NarahubungRiwayat extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'narahubung_riwayat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data_sebelum' => 'array', 'data_sesudah' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('narahubung_riwayat is append-only.'));
        static::deleting(fn () => throw new LogicException('narahubung_riwayat is append-only.'));
    }

    /** @return BelongsTo<Narahubung, $this> */
    public function narahubung(): BelongsTo
    {
        return $this->belongsTo(Narahubung::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /** @return BelongsTo<NarahubungUsulan, $this> */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(NarahubungUsulan::class, 'usulan_id');
    }
}
