<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\FacturasPendientes;
use App\Filament\Pages\GerenciaDashboard;
use App\Filament\Pages\GerenciaReportes;
use App\Filament\Resources\AperturaCierreDiaResource;
use App\Filament\Resources\CompraResource;
use App\Filament\Resources\CuentaTesoreriaResource;
use App\Filament\Resources\FondoSedeResource;
use App\Filament\Resources\GastoResource;
use App\Filament\Resources\LogResource;
use App\Filament\Resources\MotivoResource;
use App\Filament\Resources\MovimientoTesoreriaResource;
use App\Filament\Resources\PrestamoBancarioResource;
use App\Filament\Resources\TransferenciaSedeResource;
use App\Filament\Widgets\CajaAbiertaWidget;
use App\Filament\Widgets\CajaChicaWidget;
use App\Filament\Widgets\CustomAccountWidget;
use App\Filament\Widgets\DashboardCobradoDiarioWidget;
use App\Filament\Widgets\DashboardCreditosVencenHoyWidget;
use App\Filament\Widgets\DashboardMisClientesActivosWidget;
use App\Filament\Widgets\DashboardMisPrestamosActivosWidget;
use App\Filament\Widgets\DashboardMiTotalPrestadoWidget;
use App\Filament\Widgets\DashboardPagosCerradosHoyWidget;
use App\Filament\Widgets\DashboardProposicionesHoyWidget;


class GerenciaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('gerencia')
            ->path('gerencia')
            ->login(Login::class)
            ->homeUrl('/gerencia')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->colors([
                'primary' => '#9fcb36',
            ])
            ->assets([
                Css::make('custom-login-css', public_path('css/login-custom.css')),
                Css::make('custom-scrollbar-css', public_path('css/custom-scrollbar.css')),
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_START,
                fn(): string => '<link rel="stylesheet" href="' . asset('css/login-custom.css') . '?v=' . filemtime(public_path('css/login-custom.css')) . '">' .
                '<link rel="stylesheet" href="' . asset('css/custom-scrollbar.css') . '?v=' . filemtime(public_path('css/custom-scrollbar.css')) . '">'
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn(): string => \Illuminate\Support\Facades\Blade::render('
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

                    {{-- VERSION MOVIL: puntito + fecha --}}
                    <div class="flex sm:hidden items-center gap-x-1 px-1.5 py-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full shadow-sm mr-1">
                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $fechaAbierta ? "bg-success-500 shadow-[0_0_5px_rgba(34,197,94,0.5)]" : "bg-danger-500 shadow-[0_0_5px_rgba(239,68,68,0.5)]" }}"></div>
                        <span class="text-[10px] font-bold text-gray-800 dark:text-gray-200 leading-none whitespace-nowrap">
                            {{ $fechaAbierta?->format("d/m/Y") ?? "CERRADO" }}
                        </span>
                        <span class="text-[10px] font-bold text-primary-600 dark:text-primary-400 leading-none truncate max-w-[65px]">{{ $sedeNombre }}</span>
                    </div>

                    {{-- VERSION DESKTOP: PANEL GERENCIA + fecha + sede --}}
                    <div class="hidden sm:flex items-center gap-x-3 px-4 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full shadow-sm mr-4">
                        <div class="flex items-center gap-x-2">
                            <span class="text-sm md:text-base font-bold text-gray-800 dark:text-gray-200 leading-none">
                                PANEL GERENCIA
                            </span>
                        </div>
                        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600"></div>
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
            ->font('Ubuntu', 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap')
            ->brandName('JALUD - Gerencia')
            ->favicon(asset('favicon-j.svg'))
            ->navigationGroups([
                'Administración',
                'Tesorería',
                'Compras y Gastos',
                'Mantenimiento',
                'Reportes',
                'Sistema',
            ])
            ->resources([
                AperturaCierreDiaResource::class,
                CompraResource::class,
                CuentaTesoreriaResource::class,
                FondoSedeResource::class,
                GastoResource::class,
                LogResource::class,
                MotivoResource::class,
                MovimientoTesoreriaResource::class,
                PrestamoBancarioResource::class,
                TransferenciaSedeResource::class,
            ])
            ->pages([
                FacturasPendientes::class,
                GerenciaDashboard::class,
                GerenciaReportes::class,
            ])
            ->databaseTransactions()
            ->widgets([
                CustomAccountWidget::class,
                DashboardMisClientesActivosWidget::class,
                DashboardMisPrestamosActivosWidget::class,
                DashboardMiTotalPrestadoWidget::class,
                DashboardCobradoDiarioWidget::class,
                DashboardPagosCerradosHoyWidget::class,
                DashboardProposicionesHoyWidget::class,
                DashboardCreditosVencenHoyWidget::class,
                CajaAbiertaWidget::class,
                CajaChicaWidget::class,
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
                \App\Http\Middleware\EnsureGerenciaSedeSession::class,
                \App\Http\Middleware\ValidarDiaAperturado::class,
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
