<?php

namespace App\Filament\Resources\NivelAprobacionResource\Pages;

use App\Filament\Resources\NivelAprobacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNivelAprobacion extends ViewRecord
{
    protected static string $resource = NivelAprobacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
