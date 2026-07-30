<?php

namespace App\Filament\Resources\CuentaTesoreriaResource\Pages;

use App\Filament\Resources\CuentaTesoreriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCuentaTesoreria extends ViewRecord
{
    protected static string $resource = CuentaTesoreriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
