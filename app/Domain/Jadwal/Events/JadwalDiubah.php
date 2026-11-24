<?php

namespace App\Domain\Jadwal\Events;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** FR-NTF-02 "perubahan jadwal". Dispatched after commit. */
final class JadwalDiubah
{
    use Dispatchable;

    public function __construct(
        public readonly Kegiatan $kegiatan,
        public readonly User $actor,
    ) {}
}
