<?php

namespace App\Domain\Master\Models;

use Database\Factories\KabupatenKotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(KabupatenKotaFactory::class)]
#[Fillable(['kode', 'nama', 'aktif'])]
class KabupatenKota extends Model
{
    use HasFactory;

    protected $table = 'kabupaten_kota';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
