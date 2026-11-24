<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Pengguna\Support\AccountRules;
use App\Domain\Pengguna\Support\UserSnapshot;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use App\Support\Session\SessionRevoker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Updates account data and, separately authorized, the role and branch (`roles.assign`).
 * A role change ends the user's sessions so the new permissions apply immediately.
 */
final class UpdateUser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, User $target, array $input): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        $input = Arr::only($input, ['name', 'email', 'telepon', 'role', 'cabang_id']);

        if (isset($input['email'])) {
            $input['email'] = mb_strtolower(trim((string) $input['email']));
        }

        $changesRole = array_key_exists('role', $input) || array_key_exists('cabang_id', $input);

        if ($changesRole) {
            $input['role'] ??= $target->role->value;
            $input['cabang_id'] = array_key_exists('cabang_id', $input) ? $input['cabang_id'] : $target->cabang_id;
        }

        $rules = array_intersect_key(AccountRules::profile($target), $input);
        $data = Validator::make($input, $changesRole ? [...$rules, ...AccountRules::role($target)] : $rules)->validate();

        return DB::transaction(function () use ($actor, $target, $data, $changesRole) {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $before = UserSnapshot::of($locked);

            $locked->fill(Arr::only($data, ['name', 'email', 'telepon']));

            if (isset($data['name'])) {
                $locked->name = trim($data['name']);
            }

            $roleChanged = false;

            if ($changesRole) {
                $role = Role::from($data['role']);
                $cabangId = AccountRules::cabangFor($role, $data['cabang_id'] ?? null);
                $roleChanged = $role !== $locked->role;

                if ($roleChanged || $cabangId !== $locked->cabang_id) {
                    Gate::forUser($actor)->authorize('assignRole', $locked);
                }

                if ($roleChanged) {
                    AccountRules::assertNotLastSuperadmin($locked);
                    AccountRules::assertNoActiveAssignment($locked, 'mengganti role');
                }

                $locked->role = $role;
                $locked->cabang_id = $cabangId;
            }

            $locked->save();

            // A changed branch for Admin Cabang changes data scope just like a role change.
            if ($roleChanged || ($locked->role === Role::AdminCabang && $before['cabang_id'] !== $locked->cabang_id)) {
                SessionRevoker::revoke($locked);
            }

            AuditWriter::record('user.update', $actor, $locked, null, $before, UserSnapshot::of($locked));

            return $locked;
        });
    }
}
