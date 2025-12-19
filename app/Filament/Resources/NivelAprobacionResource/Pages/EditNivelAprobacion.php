<?php

namespace App\Filament\Resources\NivelAprobacionResource\Pages;

use App\Filament\Resources\NivelAprobacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNivelAprobacion extends EditRecord
{
    protected static string $resource = NivelAprobacionResource::class;

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
