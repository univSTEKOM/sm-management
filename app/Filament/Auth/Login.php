<?php

namespace App\Filament\Auth;

use App\Support\Audit\AuditWriter;
use App\Support\Auth\LoginThrottle;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Login page with the TD-09 lockout. Filament's own throttle (5 per minute per IP) is
 * replaced by {@see LoginThrottle}, which counts per account, per account + IP and per IP.
 * A failed login always shows the same generic message (AC-AUTH-02).
 */
class Login extends BaseLogin
{
    public const MESSAGE = 'Email/username atau password salah.';

    public function authenticate(): ?LoginResponse
    {
        $email = (string) ($this->data['email'] ?? '');
        $wait = LoginThrottle::secondsBlocked($email, $this->clientIp());

        if ($wait > 0) {
            AuditWriter::record('login_diblokir', null, 'users', null, null, ['email' => LoginThrottle::identity($email)]);

            Notification::make()
                ->title('Terlalu banyak percobaan masuk')
                ->body('Coba lagi dalam '.max(1, (int) ceil($wait / 60)).' menit.')
                ->danger()
                ->send();

            return null;
        }

        $response = parent::authenticate();

        if ($response !== null) {
            LoginThrottle::recordSuccess($email, $this->clientIp());
        }

        return $response;
    }

    /** Filament's per-IP throttle is superseded by LoginThrottle. */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void {}

    /** @param  array<string, mixed>  $credentials */
    protected function fireFailedEvent(Guard $guard, ?Authenticatable $user, #[SensitiveParameter] array $credentials): void
    {
        parent::fireFailedEvent($guard, $user, $credentials);

        LoginThrottle::recordFailure((string) ($credentials['email'] ?? ''), $this->clientIp());
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages(['data.email' => self::MESSAGE]);
    }

    private function clientIp(): string
    {
        return (string) request()->ip();
    }
}
