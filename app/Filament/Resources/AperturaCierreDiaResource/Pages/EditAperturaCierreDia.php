<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAperturaCierreDia extends EditRecord
{
    protected static string $resource = AperturaCierreDiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Si cambia a CERRADO desde ABIERTO
        if ($data['EstadoDia'] === 'CERRADO' && $this->record->EstadoDia === 'ABIERTO') {
            $data['UsuarioCierreID'] = auth()->id();
            $data['FechaCierre'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
