<?php

namespace App\Filament\Cumplimiento\Resources\RosCasoResource\Pages;

use App\Filament\Cumplimiento\Resources\RosCasoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRosCaso extends EditRecord
{
    protected static string $resource = RosCasoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
