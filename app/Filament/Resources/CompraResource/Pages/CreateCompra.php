<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['Activo'] = true;
        $data['Total'] = 0;
        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
    }

    protected function afterCreate(): void
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra registrada correctamente';
    }
}
