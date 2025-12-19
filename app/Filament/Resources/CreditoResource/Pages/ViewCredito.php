<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCredito extends ViewRecord
{
    protected static string $resource = CreditoResource::class;

    protected static ?string $title = 'Ver Crédito';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar explícitamente la relación proposicion si no está cargada
        $record = $this->record;
        if (!$record->relationLoaded('proposicion')) {
            $record->load('proposicion.cliente');
        }

        $proposicion = $record->proposicion;

        // Inyectar los datos de la proposición manualmente
        if ($proposicion) {
            $data['proposicion_codigocredito'] = $proposicion->CodigoCredito;
            $data['proposicion_cliente_nombre'] = $proposicion->cliente?->NombresApellidos ?? '-';
            $data['proposicion_cliente_dni'] = $proposicion->cliente?->DNI ?? '-';
            $data['proposicion_monto'] = $proposicion->MontoTotal ?? 0;
            $data['proposicion_tasa'] = $proposicion->TasaInteres ?? 0;
            $data['proposicion_plazo'] = $proposicion->Plazo ?? 0;
            $data['proposicion_cuotas'] = $proposicion->NumeroCuotas ?? 0;
            $data['proposicion_monto_cuota'] = $proposicion->MontoCuota ?? 0;
            $data['proposicion_interes'] = $proposicion->MontoInteres ?? 0;
            $data['proposicion_mora'] = $proposicion->TasaMora ?? 0;
        }

        return $data;
    }
}
