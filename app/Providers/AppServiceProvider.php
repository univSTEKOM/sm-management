<?php

namespace App\Providers;

use App\Domain\Jadwal\Models\Kegiatan;
use App\Domain\Master\Models\AppSetting;
use App\Domain\Master\Support\MasterRegistry;
use App\Domain\Sekolah\Models\NarahubungUsulan;
use App\Domain\Sekolah\Models\Sekolah;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Listeners\SendSekolahNotifications;
use App\Models\User;
use App\Policies\AppSettingPolicy;
use App\Policies\KegiatanPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\NarahubungUsulanPolicy;
use App\Policies\SekolahPolicy;
use App\Policies\UserPolicy;
use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(NarahubungUsulan::class, NarahubungUsulanPolicy::class);
        Gate::policy(Sekolah::class, SekolahPolicy::class);
        Gate::policy(Kegiatan::class, KegiatanPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AppSetting::class, AppSettingPolicy::class);

        foreach (MasterRegistry::types() as $masterType) {
            Gate::policy($masterType, MasterDataPolicy::class);
        }

        Event::subscribe(SendSekolahNotifications::class);

        $this->registerAuthAudit();
    }

    /** FR-AUD-01: login, logout and failed logins are audited. */
    private function registerAuthAudit(): void
    {
        Event::listen(function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            if (app()->bound('session.store')) {
                session()->put(EnforceIdleTimeout::SESSION_KEY, now()->getTimestamp());
            }

            $event->user->forceFill(['last_login_at' => now(), 'gagal_login' => 0, 'dikunci_sampai' => null])->saveQuietly();
            AuditWriter::record('login', $event->user, $event->user);
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                AuditWriter::record('logout', $event->user, $event->user);
            }
        });

        Event::listen(function (Failed $event): void {
            $user = $event->user instanceof User ? $event->user : null;

            AuditWriter::record('login_gagal', $user, 'users', $user?->id, null, [
                'email' => $event->credentials['email'] ?? null,
                // AC-AUTH-03: the trail shows why a valid password was still refused.
                'alasan' => $user !== null && ! $user->aktif ? 'akun_nonaktif' : null,
            ]);
        });
    }
}
