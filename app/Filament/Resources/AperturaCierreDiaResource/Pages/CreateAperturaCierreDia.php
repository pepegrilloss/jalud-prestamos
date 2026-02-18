<?php

namespace App\Filament\Resources\AperturaCierreDiaResource\Pages;

use App\Filament\Resources\AperturaCierreDiaResource;
use App\Models\AperturaCierreDia;
use App\Events\DiaAbierto;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAperturaCierreDia extends CreateRecord
{
    protected static string $resource = AperturaCierreDiaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si es ABIERTO, asignar usuario y timestamp
        if ($data['EstadoDia'] === 'ABIERTO') {
            $data['UsuarioAperturaID'] = auth()->id();
            $data['FechaApertura'] = now();
        }

        // Si es CERRADO, asignar usuario y timestamp
        if ($data['EstadoDia'] === 'CERRADO') {
            $data['UsuarioCierreID'] = auth()->id();
            $data['FechaCierre'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Disparar evento manualmente si se creó como ABIERTO
        if ($this->record->EstadoDia === 'ABIERTO') {
            DiaAbierto::dispatch($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
