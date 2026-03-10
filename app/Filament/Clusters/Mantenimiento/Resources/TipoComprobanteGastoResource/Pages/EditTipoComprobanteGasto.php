<?php

namespace App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource\Pages;

use App\Filament\Clusters\Mantenimiento\Resources\TipoComprobanteGastoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoComprobanteGasto extends EditRecord
{
    protected static string $resource = TipoComprobanteGastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
