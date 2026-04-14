<?php

namespace App\Filament\Resources\ResolucionExcedenteResource\Pages;

use App\Filament\Resources\ResolucionExcedenteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateResolucionExcedente extends CreateRecord
{
    protected static string $resource = ResolucionExcedenteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['UserSolicitanteID'] = auth()->id();
        
        if (auth()->check() && !empty(auth()->user()->SedeID)) {
            $data['SedeID'] = auth()->user()->SedeID;
        }

        return $data;
    }
}
