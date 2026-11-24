<?php

namespace App\Domain\Laporan\Events;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Models\User;
use App\Support\Progress\ProgressResult;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after a report/status change commits; carries derived transitions. */
final class StatusKegiatanDiperbarui
{
    use Dispatchable;

    public function __construct(
        public readonly Kegiatan $kegiatan,
        public readonly User $actor,
        public readonly ProgressResult $progress,
    ) {}
}
