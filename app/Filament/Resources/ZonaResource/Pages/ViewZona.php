<?php

namespace App\Filament\Resources\ZonaResource\Pages;

use App\Filament\Resources\ZonaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewZona extends ViewRecord
{
    protected static string $resource = ZonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
