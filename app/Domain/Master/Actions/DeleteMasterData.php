<?php

namespace App\Domain\Master\Actions;

use App\Domain\Master\Support\MasterRegistry;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Deletes master data that was never used (FR-MST-02). Used records must be
 * deactivated; foreign keys ON DELETE RESTRICT are the database backstop.
 */
final class DeleteMasterData
{
    public function handle(User $actor, Model $record): void
    {
        if (! MasterRegistry::supports($record)) {
            throw new InvalidArgumentException('Unsupported master type ['.$record::class.'].');
        }

        DB::transaction(function () use ($actor, $record) {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (MasterRegistry::isUsed($locked)) {
                throw DomainRuleViolation::withMessage('Data master ini sudah dipakai sehingga tidak dapat dihapus. Nonaktifkan saja.');
            }

            Gate::forUser($actor)->authorize('delete', $locked);

            $snapshot = $locked->attributesToArray();
            $locked->delete();

            AuditWriter::record('master.delete', $actor, $locked, null, $snapshot, null);
        });
    }
}
