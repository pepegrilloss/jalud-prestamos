<?php

namespace App\Console\Commands;

use App\Models\Log;
use App\Models\Pago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegularizarAMayorResoluciones extends Command
{
    protected $signature = 'pagos:regularizar-a-mayor-resoluciones
                            {--codigo=* : Codigo(s) de credito a auditar}
                            {--fix : Aplica la distribucion validada}
                            {--usuario-id= : Usuario responsable de la regularizacion}';

    protected $description = 'Separa pagos de resoluciones entre deuda aplicada y pago a mayor';

    private const TOLERANCIA = 0.009;

    public function handle(): int
    {
        $codigos = collect($this->option('codigo'))
            ->map(fn ($codigo) => mb_strtoupper(trim((string) $codigo)))
            ->filter()
            ->unique()
            ->values();
        $aplicar = (bool) $this->option('fix');
        $usuarioId = (int) $this->option('usuario-id');

        if ($codigos->isEmpty()) {
            $this->error('Indique al menos un credito con --codigo=C-000000.');

            return self::FAILURE;
        }

        if ($aplicar && ($usuarioId <= 0 || ! DB::table('users')->where('id', $usuarioId)->exists())) {
            $this->error('Para aplicar indique un --usuario-id existente.');

            return self::FAILURE;
        }

        try {
            $resultados = DB::transaction(function () use ($codigos, $aplicar, $usuarioId) {
                return $codigos->map(fn (string $codigo) => $this->procesarCredito($codigo, $aplicar, $usuarioId));
            }, 3);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('No se modifico ningun registro.');

            return self::FAILURE;
        }

        $this->table(
            ['Credito', 'Deuda', 'Pagos reales', 'Resoluciones', 'A mayor esperado', 'Accion'],
            $resultados->map(fn (array $r) => [
                $r['codigo'],
                number_format($r['deuda'], 2),
                number_format($r['pagos_reales'], 2),
                number_format($r['resoluciones'], 2),
                number_format($r['a_mayor'], 2),
                $r['accion'],
            ])->all()
        );

        $this->line($aplicar
            ? 'Regularizacion aplicada sin crear movimientos de caja ni cambiar el total pagado.'
            : 'Modo auditoria: no se modifico ningun registro.');

        return self::SUCCESS;
    }

    private function procesarCredito(string $codigo, bool $aplicar, int $usuarioId): array
    {
        $credito = DB::table('Credito as c')
            ->join('ProposicionCredito as pc', 'c.ProposicionCreditoID', '=', 'pc.ProposicionCreditoID')
            ->where('pc.CodigoCredito', $codigo)
            ->select('c.CreditoID', 'c.SedeID', 'pc.MontoTotalPagar')
            ->lockForUpdate()
            ->first();

        if (! $credito) {
            throw new RuntimeException("No existe el credito {$codigo}.");
        }

        $pagosReales = (float) DB::table('pago')
            ->where('CreditoID', $credito->CreditoID)
            ->where('Activo', 1)
            ->where('EsMora', 0)
            ->whereNull('SolicitudResolucionID')
            ->sum('MontoPagado');
        $grupos = Pago::withoutGlobalScope('sede')
            ->where('CreditoID', $credito->CreditoID)
            ->where('Activo', true)
            ->where('EsMora', false)
            ->whereNotNull('SolicitudResolucionID')
            ->orderBy('SolicitudResolucionID')
            ->orderBy('PagoID')
            ->lockForUpdate()
            ->get()
            ->groupBy('SolicitudResolucionID');

        if ($grupos->isEmpty()) {
            throw new RuntimeException("El credito {$codigo} no tiene pagos generados por resoluciones.");
        }

        $saldoPorCubrir = round(max(0, (float) $credito->MontoTotalPagar - $pagosReales), 2);
        $totalResoluciones = 0.0;
        $totalMayor = 0.0;
        $cambios = 0;

        foreach ($grupos as $solicitudId => $pagos) {
            $montoGrupo = round((float) $pagos->sum('MontoPagado'), 2);
            $normalEsperado = min($montoGrupo, $saldoPorCubrir);
            $mayorEsperado = round(max(0, $montoGrupo - $normalEsperado), 2);
            $saldoPorCubrir = round(max(0, $saldoPorCubrir - $normalEsperado), 2);
            $totalResoluciones += $montoGrupo;
            $totalMayor += $mayorEsperado;

            $normalActual = round((float) $pagos->where('EsPagoAMayor', false)->sum('MontoPagado'), 2);
            $mayorActual = round((float) $pagos->where('EsPagoAMayor', true)->sum('MontoPagado'), 2);
            if (abs($normalActual - $normalEsperado) <= self::TOLERANCIA
                && abs($mayorActual - $mayorEsperado) <= self::TOLERANCIA) {
                continue;
            }

            if ($pagos->count() !== 1) {
                throw new RuntimeException("La resolucion #{$solicitudId} de {$codigo} ya tiene una distribucion compleja; se omitio por seguridad.");
            }

            $pago = $pagos->first();
            if ($aplicar) {
                $this->aplicarDistribucion($pago, $normalEsperado, $mayorEsperado, $usuarioId, $codigo);
            }
            $cambios++;
        }

        return [
            'codigo' => $codigo,
            'deuda' => (float) $credito->MontoTotalPagar,
            'pagos_reales' => $pagosReales,
            'resoluciones' => round($totalResoluciones, 2),
            'a_mayor' => round($totalMayor, 2),
            'accion' => $cambios === 0 ? 'Ya consistente' : ($aplicar ? 'Corregido' : "Corregir {$cambios}"),
        ];
    }

    private function aplicarDistribucion(Pago $pago, float $normal, float $mayor, int $usuarioId, string $codigo): void
    {
        $montoOriginal = (float) $pago->MontoPagado;

        if ($normal > self::TOLERANCIA && $mayor > self::TOLERANCIA) {
            DB::table('pago')->where('PagoID', $pago->PagoID)->update([
                'MontoPagado' => $normal,
                'EsPagoAMayor' => 0,
                'EsPagoAMayorPorMora' => 0,
                'UserModificacionID' => $usuarioId,
                'FechaModificacion' => now(),
            ]);

            $nuevo = $pago->replicate(['PagoID']);
            $nuevo->MontoPagado = $mayor;
            $nuevo->EsPagoAMayor = true;
            $nuevo->EsPagoAMayorPorMora = false;
            $nuevo->CuotaID = null;
            $nuevo->UserModificacionID = $usuarioId;
            $nuevo->Comentario = "Pago a mayor separado de la resolucion #{$pago->SolicitudResolucionID}. Credito {$codigo}.";
            $nuevo->save();
        } else {
            DB::table('pago')->where('PagoID', $pago->PagoID)->update([
                'MontoPagado' => $mayor > self::TOLERANCIA ? $mayor : $normal,
                'EsPagoAMayor' => $mayor > self::TOLERANCIA,
                'EsPagoAMayorPorMora' => 0,
                'UserModificacionID' => $usuarioId,
                'FechaModificacion' => now(),
            ]);
        }

        Log::registrar(
            'REG_AM_RESOL',
            'Pago',
            $pago->PagoID,
            ['MontoPagado' => $montoOriginal, 'EsPagoAMayor' => (bool) $pago->EsPagoAMayor],
            ['MontoDeuda' => $normal, 'MontoAMayor' => $mayor, 'CodigoCredito' => $codigo],
            (int) $pago->SedeID,
            $usuarioId
        );
    }
}
