<?php

namespace App\Filament\Resources\ExcedenteResource\Pages;

use App\Filament\Resources\ExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExcedente extends CreateRecord
{
    protected static string $resource = ExcedenteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['UsuarioRegistro'] = auth()->id();
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }

    protected function afterCreate(): void
    {
        try {
            \App\Models\User::notificarAdmin(
                'Excedente registrado',
                'Nuevo excedente por S/ ' . number_format((float) $this->record->Monto, 2),
                'heroicon-o-currency-dollar',
                $this->record->SedeID
            );
        } catch (\Exception $e) {
        }
    }
}
