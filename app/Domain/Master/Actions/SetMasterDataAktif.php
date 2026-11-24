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
 * Deactivates or reactivates master data (FR-MST-02). Inactive records stay on existing
 * data but are no longer offered for new input.
 */
final class SetMasterDataAktif
{
    public function handle(User $actor, Model $record, bool $aktif): Model
    {
        if (! MasterRegistry::supports($record)) {
            throw new InvalidArgumentException('Unsupported master type ['.$record::class.'].');
        }

        Gate::forUser($actor)->authorize('update', $record);

        return DB::transaction(function () use ($actor, $record, $aktif) {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ((bool) $locked->getAttribute('aktif') === $aktif) {
                throw DomainRuleViolation::withMessage($aktif ? 'Data sudah aktif.' : 'Data sudah nonaktif.');
            }

            $locked->setAttribute('aktif', $aktif);
            $locked->save();

            AuditWriter::record($aktif ? 'master.activate' : 'master.deactivate', $actor, $locked, null, ['aktif' => ! $aktif], ['aktif' => $aktif]);

            return $locked;
        });
    }
}
