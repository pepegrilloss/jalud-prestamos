<?php

namespace App\Filament\Resources\TipoComprobanteResource\Pages;

use App\Filament\Resources\TipoComprobanteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoComprobante extends EditRecord
{
    protected static string $resource = TipoComprobanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => auth()->user()->can('delete_tipo_comprobante')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Tipo de Comprobante actualizado correctamente';
    }
}
