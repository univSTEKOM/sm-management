<?php

namespace App\Support\Concurrency;

use App\Domain\Shared\Exceptions\DomainRuleViolation;
use Illuminate\Database\Eloquent\Model;

/** Edge case E9: reject saves based on a stale version of the record. */
final class OptimisticLock
{
    public static function check(Model $record, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $record->getAttribute('version') !== $expectedVersion) {
            throw DomainRuleViolation::withMessage('Data telah diubah pengguna lain, muat ulang halaman.', 'version');
        }
    }

    public static function bump(Model $record): void
    {
        $record->setAttribute('version', (int) $record->getAttribute('version') + 1);
    }
}
