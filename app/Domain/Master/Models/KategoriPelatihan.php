<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'deskripsi', 'aktif'])]
class KategoriPelatihan extends Model
{
    protected $table = 'kategori_pelatihan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
