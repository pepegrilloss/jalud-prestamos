<?php

namespace App\Filament\Resources\PromotorCobradorResource\Pages;

use App\Filament\Resources\PromotorCobradorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotorCobrador extends EditRecord
{
    protected static string $resource = PromotorCobradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
