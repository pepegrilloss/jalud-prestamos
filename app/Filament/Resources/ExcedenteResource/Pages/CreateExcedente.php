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
        // Asignar la sede del usuario si existe
        if (auth()->check() && !empty(auth()->user()->SedeID)) {
            $data['SedeID'] = auth()->user()->SedeID;
        }

        return $data;
    }
}
