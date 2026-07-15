<?php

namespace App\Providers\Filament;

use App\Filament\Cumplimiento\Resources\RosCasoResource;
use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CumplimientoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('cumplimiento')
            ->path('cumplimiento')
            ->login(Login::class)
            ->brandName('JALUD - Cumplimiento SBS')
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('favicon-j.svg'))
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->colors(['primary' => '#9fcb36'])
            ->assets([
                Css::make('custom-login-css', public_path('css/login-custom.css')),
                Css::make('custom-scrollbar-css', public_path('css/custom-scrollbar.css')),
            ])
            ->navigationGroups(['Cumplimiento'])
            ->resources([
                RosCasoResource::class,
            ])
            ->databaseTransactions()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                \Hasnayeen\Themes\ThemesPlugin::make()
                    ->canViewThemesPage(fn() => auth()->user() ? auth()->user()->can('page_Themes') : false),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
