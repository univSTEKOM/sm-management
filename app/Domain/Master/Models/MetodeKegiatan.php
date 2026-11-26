<?php

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nama
 * @property bool $butuh_lokasi
 * @property bool $butuh_tautan
 * @property bool $aktif
 */
#[Fillable(['nama', 'butuh_lokasi', 'butuh_tautan', 'aktif'])]
class MetodeKegiatan extends Model
{
    protected $table = 'metode_kegiatan';

    protected function casts(): array
    {
        return ['butuh_lokasi' => 'boolean', 'butuh_tautan' => 'boolean', 'aktif' => 'boolean'];
    }
}
