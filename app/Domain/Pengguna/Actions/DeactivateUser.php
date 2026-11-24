<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Pengguna\Support\AccountRules;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Session\SessionRevoker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deactivates an account without deleting history (FR-USR-02, AC-USR-03) and ends its
 * sessions. A mentor with active assignments must be reassigned first (BR-MNT-04, E4).
 */
final class DeactivateUser
{
    public function handle(User $actor, User $target): User
    {
        Gate::forUser($actor)->authorize('deactivate', $target);

        return DB::transaction(function () use ($actor, $target) {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('deactivate', $locked);
            AccountRules::assertNotLastSuperadmin($locked);
            AccountRules::assertNoActiveAssignment($locked, 'menonaktifkan akun');

            $locked->aktif = false;
            $locked->save();
            $revoked = SessionRevoker::revoke($locked);

            AuditWriter::record('user.deactivate', $actor, $locked, null, ['aktif' => true], ['aktif' => false, 'sesi_dicabut' => $revoked]);

            return $locked;
        });
    }
}
