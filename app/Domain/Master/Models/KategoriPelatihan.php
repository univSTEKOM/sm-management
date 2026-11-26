<?php

namespace App\Domain\Master\Models;

use App\Domain\Sekolah\Models\Sekolah;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nama', 'deskripsi', 'aktif'])]
class KategoriPelatihan extends Model
{
    protected $table = 'kategori_pelatihan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return BelongsToMany<Sekolah, $this> */
    public function sekolah(): BelongsToMany
    {
        return $this->belongsToMany(Sekolah::class, 'sekolah_kategori', 'kategori_id', 'sekolah_id');
    }
}
