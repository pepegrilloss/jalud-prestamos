<?php

namespace App\Filament\Resources\PromotorCobradorResource\Pages;

use App\Filament\Resources\PromotorCobradorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPromotorCobrador extends ViewRecord
{
    protected static string $resource = PromotorCobradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
