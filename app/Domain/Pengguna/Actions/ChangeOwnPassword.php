<?php

namespace App\Domain\Pengguna\Actions;

use App\Domain\Pengguna\Support\AccountRules;
use App\Domain\Shared\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;

/**
 * A user changes their own password (FR-AUTH-03) and clears the first-login
 * obligation. Other sessions end through AuthenticateSession (password hash changes).
 */
final class ChangeOwnPassword
{
    public function handle(User $actor, #[SensitiveParameter] string $currentPassword, #[SensitiveParameter] string $newPassword): User
    {
        if (! Hash::check($currentPassword, $actor->password)) {
            throw DomainRuleViolation::withMessage('Password saat ini salah.', 'currentPassword');
        }

        Validator::make(['password' => $newPassword], ['password' => AccountRules::password()])->validate();

        if (Hash::check($newPassword, $actor->password)) {
            throw DomainRuleViolation::withMessage('Password baru harus berbeda dari password saat ini.', 'password');
        }

        return DB::transaction(function () use ($actor, $newPassword) {
            $actor->forceFill(['password' => $newPassword, 'wajib_ganti_password' => false])->save();

            AuditWriter::record('user.password_change', $actor, $actor, null, null, ['wajib_ganti_password' => false]);

            return $actor;
        });
    }
}
