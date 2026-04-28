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
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $record = parent::handleRecordCreation($data);

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        if ($fechaAbierta) {
            $fechaRegistro = $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second);
            $record->created_at = $fechaRegistro;
            $record->updated_at = $fechaRegistro;
            $record->saveQuietly();
        }

        return $record;
    }
}
