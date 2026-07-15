<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPrestamoBancario extends ViewRecord
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
