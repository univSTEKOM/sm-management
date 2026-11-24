<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Pengguna\Support\AccountRules;
use App\Domain\Pengguna\Support\UserSnapshot;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Creates an account (FR-USR-01, Superadmin only). The initial password must be
 * changed at first login (PRD 13.11 recommendation, TD-09 assumption).
 */
final class CreateUser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $input = Arr::only($input, ['name', 'email', 'telepon', 'role', 'cabang_id', 'password']);
        $input['email'] = isset($input['email']) ? mb_strtolower(trim((string) $input['email'])) : null;

        $data = Validator::make($input, [
            ...AccountRules::profile(),
            ...AccountRules::role(),
            'password' => AccountRules::password(),
        ])->validate();

        $role = Role::from($data['role']);

        return DB::transaction(function () use ($actor, $data, $role) {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'telepon' => $data['telepon'] ?? null,
                'role' => $role,
                'cabang_id' => AccountRules::cabangFor($role, $data['cabang_id'] ?? null),
                'password' => $data['password'],
                'aktif' => true,
                'wajib_ganti_password' => true,
            ]);

            AuditWriter::record('user.create', $actor, $user, null, null, UserSnapshot::of($user));

            return $user;
        });
    }
}
