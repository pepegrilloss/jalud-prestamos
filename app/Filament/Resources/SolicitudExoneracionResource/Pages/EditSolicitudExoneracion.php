<?php

namespace App\Filament\Resources\SolicitudExoneracionResource\Pages;

use App\Filament\Resources\SolicitudExoneracionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSolicitudExoneracion extends EditRecord
{
    protected static string $resource = SolicitudExoneracionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(false),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Solicitud de exoneración actualizada correctamente';
    }
}
