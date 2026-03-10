<?php

namespace App\Filament\Resources\MotivoResource\Pages;

use App\Filament\Resources\MotivoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotivo extends EditRecord
{
    protected static string $resource = MotivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Motivo actualizado correctamente';
    }
}
