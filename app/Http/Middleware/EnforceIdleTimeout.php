<?php

namespace App\Http\Middleware;

use App\Support\Audit\AuditWriter;
use App\Support\Session\ActivityClassifier;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side idle timeout (TD-04, Implementation Plan 4.10).
 *
 * The decision uses a timestamp that only user activity updates; passive
 * notification polling never refreshes it, and a polling request that arrives
 * after the limit ends the session on the server.
 */
class EnforceIdleTimeout
{
    public const SESSION_KEY = 'auth.last_activity_at';

    public function __construct(private readonly ActivityClassifier $classifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        $last = $session->get(self::SESSION_KEY);
        $limit = (int) config('access.idle_timeout_minutes', 60) * 60;

        if ($last !== null && $now - (int) $last > $limit) {
            AuditWriter::record('logout_idle', $user, $user);

            Auth::guard(Filament::getAuthGuard())->logout();
            $session->invalidate();
            $session->regenerateToken();

            throw new AuthenticationException('Sesi berakhir karena tidak ada aktivitas.', [], Filament::getLoginUrl());
        }

        if ($last === null || $this->classifier->isUserActivity($request)) {
            $session->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }
}
