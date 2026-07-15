<?php

namespace App\Filament\Resources\PrestamoBancarioResource\Pages;

use App\Filament\Resources\PrestamoBancarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrestamosBancarios extends ListRecords
{
    protected static string $resource = PrestamoBancarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nuevo préstamo bancario')];
    }

    protected function getHeaderWidgets(): array
    {
        return PrestamoBancarioResource::getHeaderWidgets();
    }
}
