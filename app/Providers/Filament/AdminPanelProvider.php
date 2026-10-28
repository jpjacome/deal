<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->brandName('Deal')
            ->brandLogo(fn () => view('components.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                // Hand-tuned so 600 (buttons, links) is the brand red #971c1a; Color::hex() would lighten it.
                'primary' => [
                    50 => 'oklch(0.977 0.014 27.4)',
                    100 => 'oklch(0.950 0.033 27.4)',
                    200 => 'oklch(0.905 0.063 27.4)',
                    300 => 'oklch(0.820 0.110 27.4)',
                    400 => 'oklch(0.700 0.160 27.4)',
                    500 => 'oklch(0.580 0.180 27.4)',
                    600 => 'oklch(0.457 0.161 27.4)',
                    700 => 'oklch(0.395 0.140 27.4)',
                    800 => 'oklch(0.335 0.115 23)',
                    900 => 'oklch(0.280 0.090 20)',
                    950 => 'oklch(0.200 0.065 20)',
                ],
                'gray' => Color::Stone,
                'warning' => Color::Orange,
            ])
            // Fonts are self-hosted by Vite (@fonts); the brand skin lives in resources/css/admin.css.
            ->font('Inter', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render("@fonts\n@vite('resources/css/admin.css')"))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups(['Reservas', 'Configuración'])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ]);
    }
}
