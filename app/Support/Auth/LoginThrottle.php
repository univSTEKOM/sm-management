<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login brute-force policy (TD-09, S-04). Three independent layers:
 *
 *  1. Per account: after MAX_PER_ACCOUNT consecutive failures from any address the account is
 *     locked for LOCK_MINUTES (users.gagal_login / dikunci_sampai). A correct password does not
 *     bypass the lock.
 *  2. Per account + IP: the same limit counted for the email/IP pair, which also covers emails
 *     that do not exist (the response is identical, so account existence does not leak).
 *  3. Per IP: a generous ceiling against mass guessing across many accounts. It is high enough
 *     for many staff behind one public IP (a school or office) not to block each other.
 *
 * Only failures count; successful logins do not consume any budget.
 */
final class LoginThrottle
{
    public const MAX_PER_ACCOUNT = 5;

    public const LOCK_MINUTES = 15;

    public const MAX_PER_IP = 50;

    public static function identity(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** Seconds the attempt must wait, or 0 when it may proceed. */
    public static function secondsBlocked(string $email, string $ip): int
    {
        $email = self::identity($email);
        $wait = 0;

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();

            if ($user?->dikunci_sampai !== null && $user->dikunci_sampai->isFuture()) {
                $wait = (int) max(1, now()->diffInSeconds($user->dikunci_sampai, true));
            }

            if (RateLimiter::tooManyAttempts(self::pairKey($email, $ip), self::MAX_PER_ACCOUNT)) {
                $wait = max($wait, RateLimiter::availableIn(self::pairKey($email, $ip)));
            }
        }

        if (RateLimiter::tooManyAttempts(self::ipKey($ip), self::MAX_PER_IP)) {
            $wait = max($wait, RateLimiter::availableIn(self::ipKey($ip)));
        }

        return $wait;
    }

    public static function recordFailure(string $email, string $ip): void
    {
        $email = self::identity($email);
        $decay = self::LOCK_MINUTES * 60;

        RateLimiter::hit(self::ipKey($ip), $decay);

        if ($email === '') {
            return;
        }

        RateLimiter::hit(self::pairKey($email, $ip), $decay);

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        // A lock that has run out starts a fresh count.
        $count = $user->dikunci_sampai !== null && $user->dikunci_sampai->isPast() ? 0 : (int) $user->gagal_login;
        $count++;

        $user->forceFill([
            'gagal_login' => $count,
            'dikunci_sampai' => $count >= self::MAX_PER_ACCOUNT ? now()->addMinutes(self::LOCK_MINUTES) : $user->dikunci_sampai,
        ])->saveQuietly();
    }

    public static function recordSuccess(string $email, string $ip): void
    {
        RateLimiter::clear(self::pairKey(self::identity($email), $ip));
    }

    private static function pairKey(string $email, string $ip): string
    {
        return 'login:pair:'.hash('sha256', $email.'|'.$ip);
    }

    private static function ipKey(string $ip): string
    {
        return 'login:ip:'.hash('sha256', $ip);
    }
}
