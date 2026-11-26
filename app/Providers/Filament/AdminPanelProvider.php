<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Pages\DashboardSekolahMitra;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\RequirePasswordChange;
use App\Support\Theme\BrandPalette;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            // Calendar (TD-13): standard MIT views only. `interaction` and every premium plugin stay unregistered.
            ->plugin(FilamentFullCalendarPlugin::make()->plugins(['dayGrid', 'timeGrid', 'list'], false)->locale('id'))
            ->globalSearch(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->colors([
                'primary' => BrandPalette::primary(),
                'gray' => BrandPalette::gray(),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                DashboardSekolahMitra::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Persistent: also applied to Livewire update requests of this panel.
            ->authMiddleware([
                EnforceIdleTimeout::class,
                RequirePasswordChange::class,
            ], isPersistent: true);
    }
}
