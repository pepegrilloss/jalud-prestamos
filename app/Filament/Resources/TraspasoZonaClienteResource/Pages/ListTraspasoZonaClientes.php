<?php

namespace App\Filament\Resources\TraspasoZonaClienteResource\Pages;

use App\Filament\Resources\TraspasoZonaClienteResource;
use Filament\Resources\Pages\ListRecords;

class ListTraspasoZonaClientes extends ListRecords
{
    protected static string $resource = TraspasoZonaClienteResource::class;

    public function getTitle(): string
    {
        return 'Historial de Traspasos de Zona';
    }
}
