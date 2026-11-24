<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Shared\Enums\Role;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Reactivates a deactivated account (Superadmin). */
final class ActivateUser
{
    public function handle(User $actor, User $target): User
    {
        Gate::forUser($actor)->authorize('activate', $target);

        return DB::transaction(function () use ($actor, $target) {
            /** @var User $locked */
            $locked = User::query()->with('cabang')->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('activate', $locked);

            if ($locked->role === Role::AdminCabang && ! $locked->cabang?->aktif) {
                throw DomainRuleViolation::withMessage('Cabang akun ini nonaktif. Pindahkan akun ke cabang aktif terlebih dahulu.');
            }

            $locked->aktif = true;
            $locked->save();

            AuditWriter::record('user.activate', $actor, $locked, null, ['aktif' => false], ['aktif' => true]);

            return $locked;
        });
    }
}
