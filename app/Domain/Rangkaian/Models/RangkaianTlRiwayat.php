<?php

namespace App\Domain\Rangkaian\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history of the follow-up series (BR-TL-09). oleh = null means the system. */
class RangkaianTlRiwayat extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'rangkaian_tl_riwayat';

    protected $guarded = ['id'];
}
