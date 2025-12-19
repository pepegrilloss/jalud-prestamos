<?php

namespace App\Filament\Resources\TipoCreditoResource\Pages;

use App\Filament\Resources\TipoCreditoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoCredito extends EditRecord
{
    protected static string $resource = TipoCreditoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['FechaModificacion'] = now();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
