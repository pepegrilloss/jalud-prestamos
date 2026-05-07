<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FondoSede;
use App\Models\MovimientoFondo;
use App\Models\TransferenciaSede;
use App\Models\Credito;
use App\Models\Pago;

class RecalcularSaldosHistoricos extends Command
{
    protected $signature = 'fondos:recalcular-historico';
    protected $description = 'Limpia los movimientos antiguos y recalcula el saldo de Trujillo asumiendo una inyección de 200,000 soles';

    public function handle()
    {
        $this->info("Iniciando recalculo de saldos históricos...");

        // Encontrar los IDs correctos dinámicamente
        $gerenciaSede = \App\Models\Sede::where('Nombre', 'like', '%Gerencia%')->first();
        $trujilloSede = \App\Models\Sede::where('Nombre', 'like', '%Trujillo%')->first();

        if (!$gerenciaSede || !$trujilloSede) {
            $this->error("No se pudo encontrar la sede de Gerencia o Trujillo en la base de datos.");
            return 1;
        }

        $gerenciaSedeId = $gerenciaSede->SedeID;
        $trujilloSedeId = $trujilloSede->SedeID;

        // Limpiar Chiclayo (SedeID 1) si se actualizó por error en la ejecución anterior
        $chiclayo = FondoSede::where('SedeID', 1)->first();
        if ($chiclayo && $gerenciaSedeId != 1) {
            $chiclayo->update(['Saldo' => 0, 'SaldoCajaChica' => 0]);
        }

        // 1. Regularizar pagos antiguos: asegurar que todos los extornos históricos sean "Cuenta a Mayor"
        Pago::whereNotNull('SolicitudResolucionID')->update(['EsPagoAMayor' => true]);
        $this->line("Pagos de extornos regularizados a Cuenta a Mayor.");

        // 2. Inyectar o corregir la transferencia inicial de 200,000 hacia Trujillo
        // Buscamos si ya existe la transferencia inicial para no duplicarla ni borrar otras reales
        $transferencia = TransferenciaSede::where('SedeDestinoID', $trujilloSedeId)
            ->where('Monto', 200000)
            ->first();

        $fechaInicial = '2026-04-06 08:00:00'; // Fecha en la que arrancaron los primeros créditos

        if ($transferencia) {
            $transferencia->update([
                'FechaTransferencia' => $fechaInicial,
                'FechaRespuesta' => $fechaInicial,
                'created_at' => $fechaInicial,
                'updated_at' => $fechaInicial
            ]);
            $this->line("Transferencia de 200,000 existente actualizada a fecha inicial: {$fechaInicial}.");
        } else {
            $transferencia = TransferenciaSede::create([
                'SedeOrigenID' => $gerenciaSedeId,
                'SedeDestinoID' => $trujilloSedeId,
                'Monto' => 200000,
                'Estado' => 'ACEPTADO',
                'UsuarioOrigenID' => 1,
                'UsuarioRespondeID' => 1,
                'FechaTransferencia' => $fechaInicial,
                'FechaRespuesta' => $fechaInicial,
                'CuentaOrigen' => 'CAJA_ABIERTA',
                'CuentaDestino' => 'CAJA_ABIERTA',
                'created_at' => $fechaInicial,
                'updated_at' => $fechaInicial
            ]);
            $this->line("Transferencia oficial de 200,000 creada en la fecha: {$fechaInicial}.");
        }

        // Buscar el movimiento de fondo inicial o crearlo
        $movimiento = MovimientoFondo::where('SedeID', $trujilloSedeId)
            ->where('Tipo', 'RECEPCION_TRANSFERENCIA')
            ->where('Monto', 200000)
            ->first();

        if ($movimiento) {
            $movimiento->update([
                'created_at' => $fechaInicial,
                'updated_at' => $fechaInicial
            ]);
        } else {
            MovimientoFondo::create([
                'SedeID' => $trujilloSedeId,
                'Tipo' => 'RECEPCION_TRANSFERENCIA',
                'Monto' => 200000,
                'SaldoAnterior' => 0,
                'SaldoNuevo' => 200000,
                'TransferenciaID' => $transferencia->TransferenciaID,
                'UsuarioID' => 1,
                'Observacion' => 'Capital inicial inyectado por Gerencia',
                'created_at' => $fechaInicial,
                'updated_at' => $fechaInicial
            ]);
        }

        // 4. Calcular prestamos de Trujillo
        $prestadoCapital = Credito::withoutGlobalScopes()
            ->whereHas('proposicion', function($q) use ($trujilloSedeId) { $q->where('SedeID', $trujilloSedeId); })
            ->where('Activo', true)
            ->with('proposicion')
            ->get()
            ->sum(function($c) { return $c->proposicion->MontoTotal; });

        $saldoActual = 200000 - $prestadoCapital;

        MovimientoFondo::create([
            'SedeID' => $trujilloSedeId,
            'Tipo' => 'EGRESO_COLOCACION',
            'Monto' => -$prestadoCapital,
            'SaldoAnterior' => 200000,
            'SaldoNuevo' => $saldoActual,
            'UsuarioID' => 1,
            'Observacion' => 'Histórico: Consolidado de todos los créditos emitidos'
        ]);

        // 5. Calcular pagos recaudados en Trujillo (SOLO DINERO FÍSICO REAL)
        // Ignoramos los pagos virtuales generados por Extornos/Excedentes (EsPagoAMayor = true)
        $pagosRecaudados = Pago::where('SedeID', $trujilloSedeId)
            ->where('Activo', true)
            ->where('EsPagoAMayor', false)
            ->sum('MontoPagado');

        // Calcular los Pagos a Mayor solo para reporte en consola
        $pagosAMayor = Pago::where('SedeID', $trujilloSedeId)
            ->where('Activo', true)
            ->where('EsPagoAMayor', true)
            ->sum('MontoPagado');

        $saldoFinal = $saldoActual + $pagosRecaudados;

        MovimientoFondo::create([
            'SedeID' => $trujilloSedeId,
            'Tipo' => 'INGRESO_RECAUDO',
            'Monto' => $pagosRecaudados,
            'SaldoAnterior' => $saldoActual,
            'SaldoNuevo' => $saldoFinal,
            'UsuarioID' => 1,
            'Observacion' => 'Histórico: Consolidado de todos los pagos físicos recibidos'
        ]);

        // 6. Actualizar FondoSede Trujillo
        $trujillo = FondoSede::updateOrCreate(
            ['SedeID' => $trujilloSedeId],
            [
                'Saldo' => $saldoFinal,
                'SaldoCajaChica' => 0
            ]
        );

        $this->info("=================================================");
        $this->info(" RECALCULO COMPLETADO EXITOSAMENTE EN TRUJILLO");
        $this->info("=================================================");
        $this->line("Inyección Base        : S/ 200,000.00");
        $this->line("Créditos Otorgados    : S/ -" . number_format($prestadoCapital, 2));
        $this->line("Pagos Físicos Recup.  : S/ +" . number_format($pagosRecaudados, 2));
        $this->info("-------------------------------------------------");
        $this->line("Cuenta a Mayor (Virtual): S/  " . number_format($pagosAMayor, 2) . " (Aislado, no suma a caja)");
        $this->info("-------------------------------------------------");
        $this->info("SALDO FÍSICO EN CAJA  : S/ " . number_format($saldoFinal, 2));
        $this->info("=================================================");

        return 0;
    }
}
