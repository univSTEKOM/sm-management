<?php

namespace App\Domain\Jadwal\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after commit; the listener reloads the activity (FR-NTF-02). */
final class PelaksanaDitunjuk
{
    use Dispatchable;

    public function __construct(
        public readonly int $kegiatanId,
        public readonly int $actorId,
    ) {}
}
