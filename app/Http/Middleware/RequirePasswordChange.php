<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** First login must change the initial password (TD-09 proposal). */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->wajib_ganti_password || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $profileUrl = Filament::getProfileUrl();

        if ($profileUrl !== null && $request->url() !== $profileUrl) {
            return redirect()->to($profileUrl);
        }

        return $next($request);
    }
}
