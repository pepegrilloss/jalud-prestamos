<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Assets\Css;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\ClienteProposicionResource\Widgets\ClienteProposicionStats;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->font('Ubuntu', 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap')
            ->brandLogoHeight('3rem')
            ->sidebarCollapsibleOnDesktop()
            ->login(Login::class)
            ->maxContentWidth('full')
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->colors([
                'primary' => '#a4cb3b',
            ])
            ->assets([
                Css::make('custom-login-css', public_path('css/login-custom.css')),
                Css::make('custom-scrollbar-css', public_path('css/custom-scrollbar.css')),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn(): string => '<link rel="stylesheet" href="' . asset('css/login-custom.css') . '?v=' . time() . '">' .
                '<link rel="stylesheet" href="' . asset('css/custom-scrollbar.css') . '?v=' . time() . '">'
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => Blade::render('@livewire("sede-switcher")')
            )
            ->favicon(asset('favicon-j.svg'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                ClienteProposicionStats::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,

            ])

            ->plugins([
                FilamentShieldPlugin::make(),
                \Hasnayeen\Themes\ThemesPlugin::make()
                    ->canViewThemesPage(fn() => auth()->user() ? auth()->user()->can('page_Themes') : false),
            ])
            ->tenantMiddleware([
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}