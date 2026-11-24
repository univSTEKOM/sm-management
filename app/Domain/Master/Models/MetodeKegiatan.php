<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'butuh_lokasi', 'butuh_tautan', 'aktif'])]
class MetodeKegiatan extends Model
{
    protected $table = 'metode_kegiatan';

    protected function casts(): array
    {
        return ['butuh_lokasi' => 'boolean', 'butuh_tautan' => 'boolean', 'aktif' => 'boolean'];
    }
}
