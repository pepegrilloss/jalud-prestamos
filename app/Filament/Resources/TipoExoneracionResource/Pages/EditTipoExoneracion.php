<?php

namespace App\Filament\Resources\TipoExoneracionResource\Pages;

use App\Filament\Resources\TipoExoneracionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoExoneracion extends EditRecord
{
    protected static string $resource = TipoExoneracionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => auth()->user()->can('delete_tipo_exoneracion')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Tipo de Exoneración actualizado correctamente';
    }
}
