<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'aktif'])]
class JenisKegiatan extends Model
{
    protected $table = 'jenis_kegiatan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
