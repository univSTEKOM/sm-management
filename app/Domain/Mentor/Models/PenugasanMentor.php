<?php

namespace App\Domain\Mentor\Models;

use App\Domain\Sekolah\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sekolah_id
 * @property int $mentor_id
 * @property Carbon $mulai
 * @property Carbon|null $selesai
 */
class PenugasanMentor extends Model
{
    protected $table = 'penugasan_mentor';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mulai' => 'datetime', 'selesai' => 'datetime'];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
