<?php

namespace App\Domain\Sekolah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Narahubung extends Model
{
    protected $table = 'narahubung';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_utama' => 'boolean', 'aktif' => 'boolean'];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
