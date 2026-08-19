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
            ->databaseNotifications(fn() => auth()->user()?->esAdmin() ?? false)
            ->databaseNotificationsPolling('60s')
            ->colors([
                'primary' => '#9fcb36',
            ])
            ->assets([
                Css::make('custom-login-css', public_path('css/login-custom.css')),
                Css::make('custom-scrollbar-css', public_path('css/custom-scrollbar.css')),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn(): string => '<link rel="stylesheet" href="' . asset('css/login-custom.css') . '?v=' . filemtime(public_path('css/login-custom.css')) . '">'
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => Blade::render('
                    @php
                        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                        $sedeId = session("sede_activa");
                        $sedeNombre = $sedeId ? \App\Models\Sede::find($sedeId)?->Nombre : "GLOBAL";
                        $puedeCambiarSede = auth()->user()->esAdmin() || auth()->user()->can("page_SelectSede") || auth()->user()->puedeVerTodasLasSedes() || auth()->user()->puedeSeleccionarSedesOperativas();
                    @endphp

                    <style>
                        @media (max-width: 639px) {
                            .fi-global-search { display: none !important; }
                        }
                    </style>

                    {{-- VERSION MOVIL: puntito + fecha + sede (compacto) --}}
                    <div class="flex sm:hidden items-center gap-x-1 px-1.5 py-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full shadow-sm mr-1">
                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $fechaAbierta ? "bg-success-500 shadow-[0_0_5px_rgba(34,197,94,0.5)]" : "bg-danger-500 shadow-[0_0_5px_rgba(239,68,68,0.5)]" }}"></div>
                        <span class="text-[10px] font-bold text-gray-800 dark:text-gray-200 leading-none whitespace-nowrap">
                            {{ $fechaAbierta?->format("d/m/Y") ?? "CERRADO" }}
                        </span>
                        <span class="text-[10px] font-bold text-primary-600 dark:text-primary-400 leading-none truncate max-w-[65px]">
                            {{ $sedeNombre }}
                        </span>
                    </div>

                    {{-- VERSION DESKTOP: fecha completa + sede --}}
                    <div class="hidden sm:flex items-center gap-x-3 px-4 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full shadow-sm mr-4">
                        <div class="flex items-center gap-x-2">
                            <div class="w-2.5 h-2.5 rounded-full {{ $fechaAbierta ? "bg-success-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]" : "bg-danger-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]" }}"></div>
                            <span class="text-sm md:text-base font-bold text-gray-800 dark:text-gray-200 leading-none">
                                {{ $fechaAbierta?->format("d/m/Y") ?? "CERRADO" }}
                            </span>
                        </div>
                        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600"></div>
                        <div class="flex items-center leading-none">
                            <span class="text-sm md:text-base font-bold text-primary-600 dark:text-primary-400 max-w-[120px] md:max-w-none truncate">
                                {{ $sedeNombre }}
                            </span>
                            @if($puedeCambiarSede)
                                <a href="{{ route("filament.admin.pages.select-sede") }}" 
                                   class="ml-2 p-1 flex-shrink-0 text-gray-400 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-all" 
                                   title="Cambiar Sede">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                ')
            )

            ->favicon(asset('favicon-j.svg'))
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): string => Blade::render('
                    <div x-show="$store.sidebar.isOpen" class="px-2 pb-4 pt-4">
                        <div class="relative group">
                            <span class="absolute inset-y-0 left-0 flex items-center pointer-events-none" style="padding-left: 1rem;">
                                <svg class="w-5 h-5 text-gray-400 group-focus-within:text-[#9fcb36] transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </span>
                            <input type="text" 
                                   id="sidebar-search-input"
                                   placeholder="Buscar en el menú..." 
                                   onkeyup="filterSidebarItems(this.value)"
                                   class="block w-full pr-4 py-2.5 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-4 focus:ring-[#9fcb36]/20 focus:border-[#9fcb36] transition-all outline-none text-gray-800 dark:text-gray-100 placeholder:text-gray-400 shadow-sm"
                                   style="padding-left: 3.5rem !important; font-family: \'Ubuntu\', sans-serif;">
                        </div>
                    </div>
                    <script>
                        function filterSidebarItems(query) {
                            const items = document.querySelectorAll(\'.fi-sidebar-item\');
                            const groups = document.querySelectorAll(\'.fi-sidebar-group\');
                            const searchQuery = query.toLowerCase().trim();
                            
                            items.forEach(item => {
                                const text = item.innerText.toLowerCase();
                                if (text.includes(searchQuery)) {
                                    item.style.display = \'flex\';
                                    item.style.opacity = \'1\';
                                } else {
                                    item.style.display = \'none\';
                                }
                            });
                            
                            groups.forEach(group => {
                                const visibleItems = group.querySelectorAll(\'.fi-sidebar-item:not([style*="display: none"])\');
                                if (visibleItems.length > 0 || searchQuery === \'\') {
                                    group.style.display = \'block\';
                                } else {
                                    group.style.display = \'none\';
                                }
                            });
                        }
                    </script>
                ')
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Balance Diario')
                    ->icon('heroicon-o-document-chart-bar')
                    ->group('Reportes')
                    ->sort(1)
                    ->url('#balance-diario')
                    ->visible(fn(): bool => auth()->user()?->can('balance_diario') ?? false),
                \Filament\Navigation\NavigationItem::make('Reporte de Cartera')
                    ->icon('heroicon-o-chart-pie')
                    ->group('Reportes')
                    ->sort(2)
                    ->url('#reporte-cartera')
                    ->visible(fn(): bool => auth()->user()?->can('reporte_cartera') ?? false),
                \Filament\Navigation\NavigationItem::make('Reporte de Creditos')
                    ->icon('heroicon-o-banknotes')
                    ->group('Reportes')
                    ->sort(3)
                    ->url('#reporte-creditos')
                    ->visible(fn(): bool => auth()->user()?->can('reporte_creditos') ?? false),
                \Filament\Navigation\NavigationItem::make('Eficiencia de cobranza')
                    ->icon('heroicon-o-chart-bar-square')
                    ->group('Reportes')
                    ->sort(4)
                    ->url('#eficiencia-cobranza')
                    ->visible(fn(): bool => auth()->user()?->can('eficiencia_cobranza') ?? false),
                \Filament\Navigation\NavigationItem::make('Cartera General')
                    ->icon('heroicon-o-bars-3-bottom-left')
                    ->group('Reportes')
                    ->sort(5)
                    ->url('#cartera-general')
                    ->visible(fn(): bool => auth()->user()?->can('reporte_cartera_general') ?? false),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render('@livewire(\App\Livewire\BalanceDiarioModal::class)')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render('@livewire(\App\Livewire\ReporteCarteraModal::class)')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render('@livewire(\App\Livewire\ReporteCreditosModal::class)')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render('@livewire(\App\Livewire\EficienciaCobranzaModal::class)')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => Blade::render('@livewire(\App\Livewire\CarteraGeneralModal::class)')
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->databaseTransactions()
            ->widgets([
                \App\Filament\Widgets\MontoPropuestoHoyStatsWidget::class,
                \App\Filament\Widgets\DashboardMisClientesActivosWidget::class,
                \App\Filament\Widgets\DashboardMisPrestamosActivosWidget::class,
                \App\Filament\Widgets\DashboardMiTotalPrestadoWidget::class,
                \App\Filament\Widgets\PagosCantidadStatsWidget::class,
                \App\Filament\Widgets\PagosMontoStatsWidget::class,
                \App\Filament\Widgets\PagosMontoMesStatsWidget::class,
                \App\Filament\Widgets\ExcedenteYapeStatsWidget::class,
                \App\Filament\Widgets\ExcedentePromotorStatsWidget::class,
                \App\Filament\Widgets\ExcedenteOficinaStatsWidget::class,
                \App\Filament\Widgets\ExcedenteTotalStatsWidget::class,
                \App\Filament\Widgets\CreditoGeneradoTotalWidget::class,
                \App\Filament\Widgets\CreditoGeneradoCantidadWidget::class,
                \App\Filament\Widgets\GenerarCreditosTotalWidget::class,
                \App\Filament\Widgets\GenerarCreditosCantidadWidget::class,
                \App\Filament\Widgets\SolicitudesPendientesSedeWidget::class,
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
                \App\Http\Middleware\EnsureSedeIsSelected::class,
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
