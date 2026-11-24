<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Pengguna\Support\AccountRules;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Session\SessionRevoker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;

/**
 * Superadmin sets a new temporary password (FR-AUTH-03). The user must change it at the
 * next login, existing sessions end, and failed-login counters are cleared.
 * The password itself is never written to the audit trail.
 */
final class ResetUserPassword
{
    public function handle(User $actor, User $target, #[SensitiveParameter] string $password): User
    {
        Gate::forUser($actor)->authorize('resetPassword', $target);

        Validator::make(['password' => $password], ['password' => AccountRules::password()])->validate();

        return DB::transaction(function () use ($actor, $target, $password) {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'password' => $password,
                'wajib_ganti_password' => true,
                'gagal_login' => 0,
                'dikunci_sampai' => null,
            ])->save();

            $revoked = SessionRevoker::revoke($locked);

            AuditWriter::record('user.reset_password', $actor, $locked, null, null, ['wajib_ganti_password' => true, 'sesi_dicabut' => $revoked]);

            return $locked;
        });
    }
}
