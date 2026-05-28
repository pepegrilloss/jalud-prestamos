<?php

namespace App\Filament\Pages;

use App\Models\Sede;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class GerenciaDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = null;
    protected static ?string $title = 'Escritorio Gerencia';
    protected static string $view = 'filament.pages.gerencia-dashboard';
    protected static string $routePath = '/';

    public string $sedeSeleccionada = '0';

    public function mount(): void
    {
        $value = request()->query('sede', session('gerencia_dashboard_sede', '0'));
        $this->sedeSeleccionada = $value;
        session(['gerencia_dashboard_sede' => $value]);
    }

    public function updatedSedeSeleccionada($value): void
    {
        session(['gerencia_dashboard_sede' => $value]);
        $this->redirect('/gerencia?sede=' . $value);
    }

    public function getSedes(): Collection
    {
        return Sede::where('Activo', true)
            ->orderBy('Nombre')
            ->pluck('Nombre', 'SedeID');
    }

    public function getSedeNombre(): string
    {
        $filter = $this->sedeSeleccionada;
        if ($filter === '0' || $filter === '' || $filter === null) {
            return 'Todas las Sedes';
        }
        $sede = Sede::find((int) $filter);
        return $sede?->Nombre ?? 'Sede no encontrada';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Escritorio';
    }

    public static function getNavigationSort(): ?int
    {
        return -2;
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

    public static function registerRoutes(Panel $panel): void
    {
        if ($panel->getId() !== 'gerencia') {
            return;
        }
        Route::name('pages.')->group(fn () => static::routes($panel));
    }

    public static function canAccess(): bool
    {
        if (filament()->getCurrentPanel()?->getId() !== 'gerencia') {
            return false;
        }
        $user = auth()->user();
        return $user && ($user->esAdmin() || $user->puedeVerTodasLasSedes());
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CustomAccountWidget::class,
            \App\Filament\Widgets\DashboardMisClientesActivosWidget::class,
            \App\Filament\Widgets\DashboardMisPrestamosActivosWidget::class,
            \App\Filament\Widgets\DashboardMiTotalPrestadoWidget::class,
            \App\Filament\Widgets\DashboardCobradoDiarioWidget::class,
            \App\Filament\Widgets\DashboardPagosCerradosHoyWidget::class,
            \App\Filament\Widgets\DashboardProposicionesHoyWidget::class,
            \App\Filament\Widgets\DashboardCreditosVencenHoyWidget::class,
            \App\Filament\Widgets\CajaAbiertaWidget::class,
            \App\Filament\Widgets\CajaChicaWidget::class,
        ];
    }
}
