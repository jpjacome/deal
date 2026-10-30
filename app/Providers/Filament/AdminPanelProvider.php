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
            // Palette from the brand moodboard only: orange → dark red gradient, cream, gray, white. No black:
            // the gray scale ends in dark red, which also makes dark mode a deep wine instead of black.
            ->colors([
                // Cream tints → orange #dd572f (400) → red (500) → dark red #4a0e14 (600, buttons and links).
                'primary' => $brand = [
                    50 => 'oklch(0.973 0.011 45.7)',
                    100 => 'oklch(0.935 0.029 45.0)',
                    200 => 'oklch(0.859 0.062 42.2)',
                    300 => 'oklch(0.757 0.111 41.6)',
                    400 => 'oklch(0.626 0.176 36.9)',
                    500 => 'oklch(0.450 0.131 31.1)',
                    600 => 'oklch(0.274 0.089 20.1)',
                    700 => 'oklch(0.243 0.077 19.7)',
                    800 => 'oklch(0.219 0.067 18.0)',
                    900 => 'oklch(0.196 0.058 17.4)',
                    950 => 'oklch(0.174 0.051 12.4)',
                ],
                // White → gray #e4e2df (200) → dark red #4a0e14 (900).
                'gray' => $gray = [
                    50 => 'oklch(0.979 0.001 106.4)',
                    100 => 'oklch(0.943 0.003 84.6)',
                    200 => 'oklch(0.914 0.005 78.3)',
                    300 => 'oklch(0.818 0.015 33.1)',
                    400 => 'oklch(0.669 0.035 22.7)',
                    500 => 'oklch(0.562 0.049 22.2)',
                    600 => 'oklch(0.465 0.063 21.1)',
                    700 => 'oklch(0.382 0.075 20.0)',
                    800 => 'oklch(0.318 0.083 20.0)',
                    900 => 'oklch(0.274 0.089 20.1)',
                    950 => 'oklch(0.243 0.077 19.7)',
                ],
                'success' => $brand,
                'info' => $gray,
                'warning' => $orange = Color::hex('#dd572f'),
                'danger' => $orange,
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
