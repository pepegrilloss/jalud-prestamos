<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
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

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }
}