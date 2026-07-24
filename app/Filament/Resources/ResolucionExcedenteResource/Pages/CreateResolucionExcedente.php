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
        if (($data['TipoResolucion'] ?? null) === 'DEVOLUCION_EFECTIVO') {
            $data['ClienteOrigenID'] = null;
            $data['PagoOrigenID'] = $data['PagoMayorOrigenID'] ?? null;
            $data['ExcedenteID'] = null;
        }
        unset($data['PagoMayorOrigenID']);

        $data['UserSolicitanteID'] = auth()->id();
        $data['SedeID'] = $data['SedeID'] ?? auth()->user()?->getEffectiveSedeId();

        $solicitud = new \App\Models\SolicitudResolucionExcedente($data);
        app(\App\Services\SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);

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

        try {
            \App\Models\User::notificarAdmin(
                'Solicitud de extorno / devolución',
                'Nueva solicitud pendiente de aprobación por S/ ' . number_format((float) $record->MontoAplicar, 2),
                'heroicon-o-arrow-path',
                $record->SedeID
            );
        } catch (\Exception $e) {
        }

        return $record;
    }
}
