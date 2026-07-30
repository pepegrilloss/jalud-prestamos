<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Models\MovimientoFondo;
use App\Models\Pago;
use App\Models\SolicitudResolucionExcedente;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrearDevolucionPagoMayor extends Command
{
    protected $signature = 'solicitudes:crear-devolucion-pago-mayor
        {codigo_credito : Codigo del credito, ejemplo C-005618}
        {monto : Monto a devolver}
        {--pago-id= : Pago a mayor origen. Si se omite, se toma el ultimo pago a mayor disponible del credito}
        {--usuario-id= : Usuario solicitante}
        {--aprobar : Aprueba la solicitud inmediatamente despues de crearla}
        {--aprobador-id= : Usuario aprobador. Si se omite, usa usuario-id}
        {--fecha-aprobacion= : Fecha contable de aprobacion en formato YYYY-MM-DD}
        {--vale= : Datos del vale de egreso}
        {--obs= : Observaciones}
        {--dry-run : Solo valida y muestra lo que crearia}';

    protected $description = 'Crea una solicitud pendiente de devolucion de efectivo A Mayor desde un pago a mayor existente.';

    public function handle(): int
    {
        $codigoCredito = trim((string) $this->argument('codigo_credito'));
        $monto = round((float) $this->argument('monto'), 2);

        if ($monto <= 0) {
            $this->error('El monto debe ser mayor a 0.');
            return self::FAILURE;
        }

        $credito = Credito::withoutGlobalScope('sede')
            ->with('proposicion.cliente')
            ->whereHas('proposicion', fn($query) => $query->where('CodigoCredito', $codigoCredito))
            ->first();

        if (!$credito || !$credito->proposicion || !$credito->proposicion->cliente) {
            $this->error("No se encontro el credito {$codigoCredito} con cliente asociado.");
            return self::FAILURE;
        }

        $pago = $this->resolverPagoOrigen($credito, $monto);
        if (!$pago) {
            $this->error('No se encontro un pago a mayor disponible suficiente para ese monto.');
            return self::FAILURE;
        }

        $disponible = $this->montoDisponiblePagoMayor($pago);
        if ($monto > round($disponible, 2)) {
            $this->error('El monto no puede superar el disponible del pago a mayor: S/ ' . number_format($disponible, 2));
            return self::FAILURE;
        }

        $usuarioId = $this->option('usuario-id') ? (int) $this->option('usuario-id') : null;
        if ($usuarioId && !User::whereKey($usuarioId)->exists()) {
            $this->error("No existe el usuario {$usuarioId}.");
            return self::FAILURE;
        }

        $aprobadorId = $this->option('aprobador-id') ? (int) $this->option('aprobador-id') : $usuarioId;
        $aprobador = null;
        if ($this->option('aprobar')) {
            if (!$aprobadorId) {
                $this->error('Para aprobar debe indicar --aprobador-id o --usuario-id.');
                return self::FAILURE;
            }

            $aprobador = User::find($aprobadorId);
            if (!$aprobador) {
                $this->error("No existe el aprobador {$aprobadorId}.");
                return self::FAILURE;
            }
        }

        $fechaAprobacion = null;
        if ($this->option('fecha-aprobacion')) {
            try {
                $fechaAprobacion = Carbon::createFromFormat('Y-m-d', (string) $this->option('fecha-aprobacion'))
                    ->setTime(12, 0, 0);
            } catch (\Throwable) {
                $this->error('La fecha de aprobacion debe tener formato YYYY-MM-DD.');
                return self::FAILURE;
            }
        }

        $data = [
            'TipoResolucion' => 'DEVOLUCION_PAGO_MAYOR',
            'ClienteOrigenID' => null,
            'ClienteDestinoID' => $credito->proposicion->ClienteID,
            'CreditoDestinoID' => $credito->CreditoID,
            'PagoOrigenID' => $pago->PagoID,
            'ExcedenteID' => null,
            'MontoAplicar' => $monto,
            'DatosValeCaja' => $this->option('vale') ?: 'Regularizacion por devolucion de efectivo A Mayor',
            'Observaciones' => $this->option('obs') ?: null,
            'Estado' => 'PENDIENTE',
            'UserSolicitanteID' => $usuarioId,
            'SedeID' => $credito->SedeID,
        ];

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Credito', $credito->proposicion->CodigoCredito],
                ['Cliente', $credito->proposicion->cliente->NombresApellidos],
                ['Pago origen', $pago->PagoID . ' - S/ ' . number_format((float) $pago->MontoPagado, 2)],
                ['Disponible', 'S/ ' . number_format($disponible, 2)],
                ['Monto solicitud', 'S/ ' . number_format($monto, 2)],
                ['SedeID', $credito->SedeID],
                ['Usuario solicitante', $usuarioId ?: 'NULL'],
                ['Estado', $this->option('aprobar') ? 'APROBADA' : 'PENDIENTE'],
                ['Aprobador', $aprobadorId ?: 'NULL'],
                ['Fecha aprobacion', $fechaAprobacion?->format('Y-m-d H:i:s') ?: 'No aplica'],
            ]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se creo ningun registro.');
            return self::SUCCESS;
        }

        $solicitud = DB::transaction(function () use ($data) {
            $solicitud = new SolicitudResolucionExcedente($data);
            app(\App\Services\SedeIntegrityService::class)->assertSolicitudResolucionConsistente($solicitud);
            $solicitud->save();

            return $solicitud;
        });

        if ($this->option('aprobar')) {
            app(\App\Services\ResolucionExcedenteService::class)->aprobar($solicitud, $aprobador);

            if ($fechaAprobacion) {
                $solicitud->forceFill([
                    'created_at' => $fechaAprobacion,
                    'updated_at' => $fechaAprobacion,
                ])->saveQuietly();

                MovimientoFondo::withoutGlobalScope('sede')
                    ->where('Tipo', 'EGRESO_DEVOLUCION_EFECTIVO')
                    ->where('SedeID', $solicitud->SedeID)
                    ->where('UsuarioID', $aprobador->id)
                    ->where('Observacion', 'like', "%solicitud #{$solicitud->SolicitudID}%")
                    ->latest('MovimientoID')
                    ->limit(1)
                    ->update([
                        'FechaMovimiento' => $fechaAprobacion,
                        'created_at' => $fechaAprobacion,
                        'updated_at' => $fechaAprobacion,
                    ]);
            }

            $this->info("Solicitud creada y aprobada correctamente: #{$solicitud->SolicitudID}");
            $this->info('Se registro el egreso de caja por devolucion de efectivo A Mayor.');

            return self::SUCCESS;
        }

        $this->info("Solicitud creada correctamente: #{$solicitud->SolicitudID}");
        $this->info('Ahora aparecera en Registro De Extornos Y Devoluciones como PENDIENTE.');

        return self::SUCCESS;
    }

    private function resolverPagoOrigen(Credito $credito, float $monto): ?Pago
    {
        $pagoId = $this->option('pago-id');

        $query = Pago::withoutGlobalScope('sede')
            ->where('CreditoID', $credito->CreditoID)
            ->where('Activo', 1)
            ->where('EsPagoAMayor', 1)
            ->where('EsPagoAMayorPorMora', 0)
            ->where(function ($query) {
                $query->whereNull('EstadoTraslado')
                    ->orWhere('EstadoTraslado', '');
            });

        if ($pagoId) {
            return (clone $query)->where('PagoID', (int) $pagoId)->first();
        }

        return $query
            ->orderByDesc('FechaPago')
            ->orderByDesc('PagoID')
            ->get()
            ->first(fn(Pago $pago) => $this->montoDisponiblePagoMayor($pago) >= $monto);
    }

    private function montoDisponiblePagoMayor(Pago $pago): float
    {
        $montoComprometido = (float) SolicitudResolucionExcedente::withoutGlobalScope('sede')
            ->where('TipoResolucion', 'DEVOLUCION_PAGO_MAYOR')
            ->where('PagoOrigenID', $pago->PagoID)
            ->where('Estado', '!=', 'RECHAZADA')
            ->sum('MontoAplicar');

        return max(0, (float) $pago->MontoPagado - $montoComprometido);
    }
}
