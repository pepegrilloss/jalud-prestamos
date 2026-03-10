<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;
        $subtotalBase = $record->detalles()->sum('Subtotal');
        $aplicaIgv = false;
        $comprobante = $record->tipoComprobante;
        if ($comprobante && in_array($comprobante->Nombre, ['FACTURA ELECTRÓNICA', 'BOLETA DE VENTA ELECTRÓNICA', 'SERVICIOS PÚBLICOS'])) {
            $aplicaIgv = true;
        }

        $igv = $aplicaIgv ? $subtotalBase * 0.18 : 0;
        $totalFinal = $subtotalBase + $igv;

        $record->update([
            'SubtotalBase' => $subtotalBase,
            'MontoIGV' => $igv,
            'Total' => $totalFinal
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function ($record) {
                    $record->update(['Activo' => false]);
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Compra actualizada correctamente';
    }
}
