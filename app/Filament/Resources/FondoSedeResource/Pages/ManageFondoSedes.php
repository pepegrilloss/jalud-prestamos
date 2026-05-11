<?php

namespace App\Filament\Resources\FondoSedeResource\Pages;

use App\Filament\Resources\FondoSedeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFondoSedes extends ManageRecords
{
    protected static string $resource = FondoSedeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\SolicitudesPendientesSedeWidget::class,
        ];
    }
}
