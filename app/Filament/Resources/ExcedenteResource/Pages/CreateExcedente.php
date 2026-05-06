<?php

namespace App\Filament\Resources\ExcedenteResource\Pages;

use App\Filament\Resources\ExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExcedente extends CreateRecord
{
    protected static string $resource = ExcedenteResource::class;
    // La asignación de SedeID se maneja automáticamente en la base a través de App\Traits\BelongsToSede

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['UsuarioRegistro'] = auth()->id();
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }
}
