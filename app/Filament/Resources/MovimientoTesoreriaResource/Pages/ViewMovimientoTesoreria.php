<?php

namespace App\Filament\Resources\MovimientoTesoreriaResource\Pages;

use App\Filament\Resources\MovimientoTesoreriaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMovimientoTesoreria extends ViewRecord
{
    protected static string $resource = MovimientoTesoreriaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
