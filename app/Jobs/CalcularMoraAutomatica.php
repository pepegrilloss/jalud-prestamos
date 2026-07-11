<?php

namespace App\Jobs;

use App\Models\Credito;
use App\Models\Mora;
use App\Services\CalendarioLaboralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalcularMoraAutomatica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fecha;

    public function __construct($fecha = null)
    {
        $this->fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : today()->toDateString();
    }

    public function handle(): void
    {
        set_time_limit(300);

        $fecha = $this->fecha ? \Carbon\Carbon::parse($this->fecha) : today();
        \Log::info('[JOB] CalcularMoraAutomatica: iniciando calculo de moras', ['fecha' => $fecha->toDateString()]);

        try {
            $morasCreadas = 0;

            Credito::withoutGlobalScope('sede')
                ->where('Activo', 1)
                ->whereDate('FechaVencimiento', '<=', $fecha)
                ->whereIn('SedeID', \App\Models\Sede::where('Activo', true)->pluck('SedeID'))
                ->with(['proposicion.cliente.tasaMora'])
                ->chunkById(500, function ($creditos) use ($fecha, &$morasCreadas) {
                    foreach ($creditos as $credito) {
                        if ($this->procesarCredito($credito, $fecha)) {
                            $morasCreadas++;
                        }
                    }
                }, 'CreditoID');

            \Log::info('[JOB] CalcularMoraAutomatica completado. Moras creadas: ' . $morasCreadas, ['fecha' => $fecha->toDateString()]);
        } catch (\Exception $e) {
            \Log::error('[JOB] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function procesarCredito(Credito $credito, \Carbon\Carbon $fecha): bool
    {
        \Log::debug('[JOB] Procesando credito: ' . $credito->CreditoID);

        if (CalendarioLaboralService::esNoLaborable($fecha, $credito->SedeID)) {
            \Log::debug('[JOB] Fecha no laborable para la sede, no se calcula mora', [
                'CreditoID' => $credito->CreditoID,
                'fecha' => $fecha->toDateString(),
                'motivo' => CalendarioLaboralService::motivoNoLaborable($fecha, $credito->SedeID),
            ]);
            return false;
        }

        $vencimientoEfectivo = CalendarioLaboralService::siguienteDiaLaborable(
            \Carbon\Carbon::parse($credito->FechaVencimiento),
            $credito->SedeID
        );

        if ($fecha->toDateString() <= $vencimientoEfectivo->toDateString()) {
            \Log::debug('[JOB] Credito ' . $credito->CreditoID . ': en periodo de gracia. Vencimiento efectivo: ' . $vencimientoEfectivo->toDateString());
            return false;
        }

        $moraHoy = Mora::where('CreditoID', $credito->CreditoID)
            ->whereDate('FechaMora', $fecha->toDateString())
            ->exists();

        if ($moraHoy) {
            \Log::debug('[JOB] Credito ' . $credito->CreditoID . ': ya tiene mora registrada para ' . $fecha->toDateString());
            return false;
        }

        $saldoPendiente = $credito->proposicion
            ? (float) ($credito->proposicion->SaldoPendiente ?? 0)
            : 0;

        if ($saldoPendiente <= 0) {
            \Log::debug('[JOB] Credito ' . $credito->CreditoID . ': saldo pendiente <= 0: ' . number_format($saldoPendiente, 2));
            return false;
        }

        $cliente = $credito->proposicion?->cliente;
        if (!$cliente) {
            \Log::warning('[JOB] Credito ' . $credito->CreditoID . ': no tiene cliente asociado');
            return false;
        }

        $porcentajeMora = $cliente->tasaMora?->Porcentaje ?? 0;

        if ($porcentajeMora <= 0) {
            \Log::warning('[JOB] Credito ' . $credito->CreditoID . ': cliente sin tasa de mora');
            return false;
        }

        $montoMora = $saldoPendiente * ($porcentajeMora / 100);

        $moraAnterior = Mora::where('CreditoID', $credito->CreditoID)
            ->orderBy('FechaMora', 'desc')
            ->first();

        $moraAcumulada = ($moraAnterior?->MoraAcumulada ?? 0) + $montoMora;

        Mora::create([
            'CreditoID' => $credito->CreditoID,
            'FechaMora' => $fecha,
            'SaldoPendiente' => $saldoPendiente,
            'PorcentajeMora' => $porcentajeMora,
            'MontoMora' => $montoMora,
            'MoraAcumulada' => $moraAcumulada,
            'SedeID' => $credito->SedeID,
        ]);

        \Log::info('[JOB] Mora calculada', [
            'CreditoID' => $credito->CreditoID,
            'ClienteID' => $cliente->ClienteID,
            'Fecha' => $fecha->toDateString(),
            'SaldoPendiente' => $saldoPendiente,
            'Porcentaje' => $porcentajeMora,
            'MontoMora' => $montoMora,
            'MoraAcumulada' => $moraAcumulada,
        ]);

        return true;
    }
}
