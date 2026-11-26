<?php

namespace App\Domain\Shared\Enums;

enum Jenjang: string
{
    case SMA = 'SMA';
    case SMK = 'SMK';
    case MA = 'MA';

    /** Neutral badge: the level is a category, not a status. */
    public function color(): string
    {
        return 'gray';
    }
}
