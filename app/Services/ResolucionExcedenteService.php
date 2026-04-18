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

            // Obtener el excedente y el monto a aplicar
            $excedente = $solicitud->excedente;
            $montoAplicar = $solicitud->MontoAplicar ?? $excedente->Monto;
            
            // Propagar el Cliente Origen identificado en la solicitud al excedente
            if ($solicitud->ClienteOrigenID) {
                $excedente->ClienteOrigenID = $solicitud->ClienteOrigenID;
            }

            // Restar el monto aplicado del excedente
            $nuevoMonto = $excedente->Monto - $montoAplicar;

            if ($nuevoMonto <= 0) {
                // Se consumió todo el excedente
                $excedente->Monto = 0;
                $excedente->EstadoResolucion = 'RESUELTO';
            } else {
                // Queda saldo disponible para futuras resoluciones
                $excedente->Monto = $nuevoMonto;
                // Sigue PENDIENTE para que se pueda asignar el resto
            }
            
            $excedente->save();

            // Calcular operaciones financieras según el tipo
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
                    'MontoPagado' => $montoAplicar,
                    'FechaPago' => Carbon::now(),
                    'TipoPago' => $tipoPago,
                    'TipoConcepto' => 'C',
                    'EsMora' => false,
                    'EsPagoAutomatico' => true,
                    'Comentario' => "Pago generado por Extorno/Resolución #{$solicitud->SolicitudID}. Tipo: {$solicitud->TipoResolucion}. Monto aplicado: S/ " . number_format($montoAplicar, 2),
                    'UsuarioRegistro' => $aprobador->name,
                    'Activo' => true,
                    'SedeID' => $solicitud->SedeID ?? $aprobador->SedeID,
                    'SolicitudResolucionID' => $solicitud->SolicitudID,
                ]);
            }
        });
    }
}
