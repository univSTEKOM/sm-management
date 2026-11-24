<?php

namespace App\Domain\Master\Models;

use Database\Factories\CabangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(CabangFactory::class)]
#[Fillable(['kode', 'nama', 'alamat', 'aktif'])]
class Cabang extends Model
{
    use HasFactory;

    protected $table = 'cabang';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
