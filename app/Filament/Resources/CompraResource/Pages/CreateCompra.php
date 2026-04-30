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

        // Calcular subtotal de cada detalle
        if (isset($data['detalles'])) {
            foreach ($data['detalles'] as &$detalle) {
                $cantidad = floatval($detalle['Cantidad'] ?? 0);
                $precio = floatval($detalle['PrecioUnitario'] ?? 0);
                $detalle['Subtotal'] = round($cantidad * $precio, 2);
            }
        }

        $subtotalBase = collect($data['detalles'] ?? [])->sum(fn($item) => floatval($item['Subtotal'] ?? 0));

        if (empty($data['SubtotalBase']) || floatval($data['SubtotalBase']) == 0) {
            $data['SubtotalBase'] = $subtotalBase;
        }

        if (empty($data['MontoIGV']) || floatval($data['MontoIGV']) == 0) {
            $data['MontoIGV'] = round($subtotalBase * 0.18, 2);
        }

        if (empty($data['Total']) || floatval($data['Total']) == 0) {
            $data['Total'] = floatval($data['SubtotalBase']) + floatval($data['MontoIGV']);
        }

        return \App\Services\DateFieldResolver::injectFechaAbierta($data, $this->getModel());
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
