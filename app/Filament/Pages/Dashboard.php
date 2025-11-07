<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;


class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CustomAccountWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }

   
}
