<?php

namespace App\Domain\Sekolah\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A mentor's proposal to change or add a contact person (BR-SCH-11). It does not change
 * any data until Admin Pusat or the school's Admin Cabang approves it.
 *
 * @property int $id
 * @property int $sekolah_id
 * @property int|null $narahubung_id
 * @property array<string, mixed>|null $data_sebelum
 * @property array<string, mixed> $data_usulan
 * @property int $diusulkan_oleh
 * @property string $status
 * @property int|null $diputuskan_oleh
 * @property string|null $alasan_penolakan
 * @property Carbon|null $diajukan_at
 * @property Carbon|null $diputuskan_at
 */
class NarahubungUsulan extends Model
{
    public const DIAJUKAN = 'diajukan';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    public const PERLU_DITINJAU = 'perlu_ditinjau';

    public const LABELS = [
        self::DIAJUKAN => 'Diajukan',
        self::DISETUJUI => 'Disetujui',
        self::DITOLAK => 'Ditolak',
        self::PERLU_DITINJAU => 'Perlu Ditinjau',
    ];

    public $timestamps = false;

    protected $table = 'narahubung_usulan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_sebelum' => 'array',
            'data_usulan' => 'array',
            'diajukan_at' => 'datetime',
            'diputuskan_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /** @return BelongsTo<Narahubung, $this> */
    public function narahubung(): BelongsTo
    {
        return $this->belongsTo(Narahubung::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diusulkan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::DIAJUKAN, self::PERLU_DITINJAU], true);
    }
}
