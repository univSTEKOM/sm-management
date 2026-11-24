<?php

namespace App\Support\Session;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every session of a user (deactivation, password reset, role change).
 * Database sessions are deleted; the remember token is rotated so "remember me"
 * cookies stop working too.
 */
final class SessionRevoker
{
    public static function revoke(User $user): int
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
