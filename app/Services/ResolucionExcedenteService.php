<?php

namespace App\Services;

use App\Models\SolicitudResolucionExcedente;
use App\Models\Pago;
use App\Models\Cuota;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResolucionExcedenteService
{
    public function aprobar(SolicitudResolucionExcedente $solicitud, $aprobador)
    {
        DB::transaction(function () use ($solicitud, $aprobador) {
            // Marcar la solicitud
            $solicitud->Estado = 'APROBADA';
            $solicitud->UserAprobadorID = $aprobador->id;
            $solicitud->save();

            // Marcar el excedente como resuelto y vincular al cliente origen
            $excedente = $solicitud->excedente;
            $excedente->EstadoResolucion = 'RESUELTO';
            
            // Propagar el Cliente Origen identificado en la solicitud al excedente
            if ($solicitud->ClienteOrigenID) {
                $excedente->ClienteOrigenID = $solicitud->ClienteOrigenID;
            }
            
            $excedente->save();

            // Calcular operaciones finacieras según el tipo
            if (in_array($solicitud->TipoResolucion, ['TRASLADO_DE_PAGO', 'ASIGNACION_POR_RECLAMO', 'APLICACION_NUEVO_CREDITO'])) {
                // Registrar el pago al nuevo credito
                $tipoPago = 'EFECTIVO';
                if ($excedente->TipoExcedente === 'YAPE_TRANSFERENCIA') {
                    $tipoPago = 'TRANSFERENCIA'; 
                }

                $cuota = Cuota::where('CreditoID', $solicitud->CreditoDestinoID)
                            ->where('Activo', 1)
                            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA'])
                            ->orderBy('NumeroCuota', 'asc')
                            ->first();

                Pago::create([
                    'CreditoID' => $solicitud->CreditoDestinoID,
                    'CuotaID' => $cuota ? $cuota->CuotaID : null,
                    'MontoPagado' => $excedente->Monto,
                    'FechaPago' => Carbon::now(),
                    'TipoPago' => $tipoPago,
                    'TipoConcepto' => 'C',
                    'EsMora' => false,
                    'EsPagoAutomatico' => true,
                    'Comentario' => "Pago generado por Extorno/Resolución #{$solicitud->SolicitudID}. Tipo: {$solicitud->TipoResolucion}",
                    'UsuarioRegistro' => $aprobador->name,
                    'Activo' => true,
                    'SedeID' => $solicitud->SedeID ?? $aprobador->SedeID,
                    'SolicitudResolucionID' => $solicitud->SolicitudID,
                ]);
            }
        });
    }
}
