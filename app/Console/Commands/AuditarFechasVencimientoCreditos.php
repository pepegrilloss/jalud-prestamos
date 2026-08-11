<?php

namespace App\Console\Commands;

use App\Models\Log as AuditLog;
use App\Services\CreditoFechaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditarFechasVencimientoCreditos extends Command
{
    protected $signature = 'creditos:auditar-fechas-vencimiento
        {--estado=ACTIVO : Estado final a revisar o TODOS}
        {--desde= : Fecha minima de generacion, formato YYYY-MM-DD}
        {--hasta= : Fecha maxima de generacion, formato YYYY-MM-DD}
        {--codigo=* : Uno o mas codigos de credito especificos}
        {--fix : Corrige FechaInicio y FechaVencimiento de creditos activos sin mora}
        {--reparar-mora : Repara la mora afectada por el vencimiento incorrecto (solo 2026)}
        {--json : Muestra el resultado completo como JSON}
        {--csv : Genera un CSV con todas las diferencias}';

    protected $description = 'Compara el vencimiento guardado con las cuotas laborables y el calendario vigente';

    public function handle(): int
    {
        $estado = strtoupper((string) $this->option('estado'));

        if ($this->option('fix') && $estado !== 'ACTIVO') {
            $this->error('Por seguridad, --fix solo puede ejecutarse con --estado=ACTIVO.');
            return self::FAILURE;
        }

        if ($this->option('reparar-mora') && ! $this->option('fix')) {
            $this->error('--reparar-mora requiere --fix.');
            return self::FAILURE;
        }

        if ($this->option('reparar-mora') && ! $this->rangoLimitadoA2026()) {
            $this->error('--reparar-mora exige --desde=2026-01-01 y --hasta=2026-12-31.');
            return self::FAILURE;
        }

        $query = DB::table('Credito as c')
            ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
            ->leftJoin('Cliente as cl', 'cl.ClienteID', '=', 'pc.ClienteID')
            ->leftJoin('Sede as s', 's.SedeID', '=', 'c.SedeID')
            ->where('c.Activo', 1)
            ->whereNotNull('c.FechaGeneracion')
            ->whereNotNull('c.FechaVencimiento')
            ->where('pc.NumeroCuotas', '>', 0);

        if ($estado !== 'TODOS') {
            $query->where('c.EstatusCreditoFinal', $estado);
        }

        if ($this->option('desde')) {
            $query->whereDate('c.FechaGeneracion', '>=', $this->option('desde'));
        }

        if ($this->option('hasta')) {
            $query->whereDate('c.FechaGeneracion', '<=', $this->option('hasta'));
        }

        if ($this->option('codigo') !== []) {
            $query->whereIn('pc.CodigoCredito', $this->option('codigo'));
        }

        $creditos = $query
            ->orderBy('c.CreditoID')
            ->get([
                'c.CreditoID',
                'pc.CodigoCredito',
                'cl.NombresApellidos as Cliente',
                's.Nombre as Sede',
                'c.SedeID',
                'c.FechaGeneracion',
                'c.FechaVencimiento',
                'c.EstatusCreditoFinal',
                'pc.NumeroCuotas',
                'pc.SaldoPendiente',
                DB::raw('(SELECT COUNT(*) FROM mora m WHERE m.CreditoID = c.CreditoID) as MorasRegistradas'),
                DB::raw("(SELECT COALESCE(SUM(p.MontoPagado), 0) FROM pago p WHERE p.CreditoID = c.CreditoID AND p.Activo = 1 AND (p.EsMora = 1 OR p.TipoConcepto = 'M')) as MoraPagada"),
            ]);

        $diferencias = [];

        foreach ($creditos as $credito) {
            $rango = CreditoFechaService::calcularRangoPorCuotasLaborables(
                $credito->FechaGeneracion,
                (int) $credito->NumeroCuotas,
                (int) $credito->SedeID
            );

            $guardada = Carbon::parse($credito->FechaVencimiento)->startOfDay();
            $inicioEsperado = $rango['FechaInicio']->startOfDay();
            $esperada = $rango['FechaVencimiento']->startOfDay();

            if ($guardada->equalTo($esperada)) {
                continue;
            }

            $diferencias[] = [
                'credito_id' => (int) $credito->CreditoID,
                'codigo' => $credito->CodigoCredito,
                'cliente' => $credito->Cliente,
                'sede' => $credito->Sede,
                'estado' => $credito->EstatusCreditoFinal,
                'saldo_pendiente' => (float) $credito->SaldoPendiente,
                'fecha_generacion' => Carbon::parse($credito->FechaGeneracion)->toDateString(),
                'numero_cuotas' => (int) $credito->NumeroCuotas,
                'inicio_esperado' => $inicioEsperado->toDateString(),
                'vencimiento_guardado' => $guardada->toDateString(),
                'vencimiento_esperado' => $esperada->toDateString(),
                'dias_diferencia' => (int) $guardada->diffInDays($esperada, false),
                'moras_registradas' => (int) $credito->MorasRegistradas,
                'mora_pagada' => (float) $credito->MoraPagada,
                'moras_eliminadas' => 0,
                'monto_mora_eliminado' => 0.0,
                'nuevo_total_mora' => null,
                'resultado' => 'PENDIENTE',
            ];
        }

        $rutaRespaldo = null;

        if ($this->option('fix') && $this->option('reparar-mora') && $diferencias !== []) {
            $rutaRespaldo = $this->generarRespaldoMoras($diferencias);
        }

        if ($this->option('fix')) {
            foreach ($diferencias as &$fila) {
                if ($fila['moras_registradas'] > 0 && ! $this->option('reparar-mora')) {
                    $fila['resultado'] = 'OMITIDO_CON_MORA';
                    continue;
                }

                if ($fila['moras_registradas'] > 0 && $fila['mora_pagada'] > 0.009) {
                    $fila['resultado'] = 'OMITIDO_MORA_PAGADA';
                    continue;
                }

                DB::transaction(function () use (&$fila): void {
                    $creditoActual = DB::table('Credito')
                        ->where('CreditoID', $fila['credito_id'])
                        ->lockForUpdate()
                        ->first(['FechaInicio', 'FechaVencimiento', 'EstatusCreditoFinal', 'SedeID']);

                    if (! $creditoActual || $creditoActual->EstatusCreditoFinal !== 'ACTIVO') {
                        $fila['resultado'] = 'OMITIDO_ESTADO_CAMBIO';
                        return;
                    }

                    $morasEliminadas = [];
                    $montoMoraEliminado = 0.0;

                    if ($fila['moras_registradas'] > 0) {
                        $moras = DB::table('mora')
                            ->where('CreditoID', $fila['credito_id'])
                            ->orderBy('FechaMora')
                            ->orderBy('MoraID')
                            ->lockForUpdate()
                            ->get();

                        $vencimientoCorrecto = Carbon::parse($fila['vencimiento_esperado'])->startOfDay();

                        foreach ($moras as $mora) {
                            $fechaMora = Carbon::parse($mora->FechaMora)->startOfDay();
                            $esValida = $fechaMora->gt($vencimientoCorrecto)
                                && \App\Services\CalendarioLaboralService::esLaborable(
                                    $fechaMora,
                                    (int) $creditoActual->SedeID
                                );

                            if ($esValida) {
                                continue;
                            }

                            $morasEliminadas[] = [
                                'MoraID' => (int) $mora->MoraID,
                                'FechaMora' => $fechaMora->toDateString(),
                                'MontoMora' => (float) $mora->MontoMora,
                                'MoraAcumulada' => (float) $mora->MoraAcumulada,
                            ];
                            $montoMoraEliminado += (float) $mora->MontoMora;
                        }

                        if ($morasEliminadas !== []) {
                            DB::table('mora')
                                ->whereIn('MoraID', array_column($morasEliminadas, 'MoraID'))
                                ->delete();
                        }
                    }

                    DB::table('Credito')
                        ->where('CreditoID', $fila['credito_id'])
                        ->update([
                            'FechaInicio' => $fila['inicio_esperado'],
                            'FechaVencimiento' => $fila['vencimiento_esperado'],
                        ]);

                    $moraAcumulada = 0.0;
                    $morasConservadas = DB::table('mora')
                        ->where('CreditoID', $fila['credito_id'])
                        ->orderBy('FechaMora')
                        ->orderBy('MoraID')
                        ->lockForUpdate()
                        ->get(['MoraID', 'MontoMora']);

                    foreach ($morasConservadas as $mora) {
                        $moraAcumulada = round($moraAcumulada + (float) $mora->MontoMora, 2);
                        DB::table('mora')
                            ->where('MoraID', $mora->MoraID)
                            ->update(['MoraAcumulada' => $moraAcumulada]);
                    }

                    AuditLog::registrar(
                        $fila['moras_registradas'] > 0 ? 'CORREGIR_MORA' : 'CORREGIR_FECHA',
                        'Credito',
                        $fila['credito_id'],
                        [
                            'FechaInicio' => $creditoActual->FechaInicio,
                            'FechaVencimiento' => $creditoActual->FechaVencimiento,
                            'MorasEliminadas' => $morasEliminadas,
                        ],
                        [
                            'FechaInicio' => $fila['inicio_esperado'],
                            'FechaVencimiento' => $fila['vencimiento_esperado'],
                            'Motivo' => 'Sincronizacion con cuotas laborables y calendario vigente',
                            'CantidadMorasEliminadas' => count($morasEliminadas),
                            'MontoMoraEliminado' => round($montoMoraEliminado, 2),
                            'NuevaMoraAcumulada' => $moraAcumulada,
                        ],
                        (int) $creditoActual->SedeID,
                        0
                    );

                    $fila['moras_eliminadas'] = count($morasEliminadas);
                    $fila['monto_mora_eliminado'] = round($montoMoraEliminado, 2);
                    $fila['nuevo_total_mora'] = $moraAcumulada;
                    $fila['resultado'] = 'CORREGIDO';
                });
            }
            unset($fila);
        }

        $rutaCsv = $this->option('csv') ? $this->generarCsv($diferencias) : null;

        if ($this->option('json')) {
            $this->line(json_encode([
                'auditados' => count($creditos),
                'total_no_cuadran' => count($diferencias),
                'fix' => (bool) $this->option('fix'),
                'corregidos' => count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'CORREGIDO')),
                'omitidos_con_mora' => count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'OMITIDO_CON_MORA')),
                'omitidos_mora_pagada' => count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'OMITIDO_MORA_PAGADA')),
                'moras_eliminadas' => array_sum(array_column($diferencias, 'moras_eliminadas')),
                'monto_mora_eliminado' => round(array_sum(array_column($diferencias, 'monto_mora_eliminado')), 2),
                'archivo_csv' => $rutaCsv,
                'archivo_respaldo' => $rutaRespaldo,
                'registros' => $diferencias,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Créditos auditados: '.count($creditos));
        $this->info('Fechas que no cuadran: '.count($diferencias));

        if ($this->option('fix')) {
            $this->info('Fechas corregidas: '.count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'CORREGIDO')));
            $omitidos = count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'OMITIDO_CON_MORA'));
            if ($omitidos > 0) {
                $this->warn("Omitidos por tener mora registrada: {$omitidos}");
            }
            $omitidosPagados = count(array_filter($diferencias, fn (array $fila): bool => $fila['resultado'] === 'OMITIDO_MORA_PAGADA'));
            if ($omitidosPagados > 0) {
                $this->warn("Omitidos por tener pagos de mora: {$omitidosPagados}");
            }
            $this->info('Registros de mora eliminados: '.array_sum(array_column($diferencias, 'moras_eliminadas')));
            $this->info('Monto de mora eliminado: S/ '.number_format(array_sum(array_column($diferencias, 'monto_mora_eliminado')), 2));
        }

        if ($rutaCsv) {
            $this->line('CSV: '.$rutaCsv);
        }

        if ($rutaRespaldo) {
            $this->line('Respaldo previo: '.$rutaRespaldo);
        }

        if ($diferencias !== []) {
            $this->table(
                ['Crédito', 'Sede', 'Generación', 'Cuotas', 'Guardado', 'Esperado', 'Días', 'Moras', 'Eliminadas', 'Resultado'],
                array_map(fn (array $fila): array => [
                    $fila['codigo'],
                    $fila['sede'],
                    $fila['fecha_generacion'],
                    $fila['numero_cuotas'],
                    $fila['vencimiento_guardado'],
                    $fila['vencimiento_esperado'],
                    $fila['dias_diferencia'],
                    $fila['moras_registradas'],
                    $fila['moras_eliminadas'],
                    $fila['resultado'],
                ], array_slice($diferencias, 0, 50))
            );

            if (count($diferencias) > 50) {
                $this->warn('La tabla muestra solo los primeros 50 resultados. Use --csv para obtener el listado completo.');
            }
        }

        return self::SUCCESS;
    }

    private function generarCsv(array $diferencias): string
    {
        $directorio = storage_path('app/auditorias');
        File::ensureDirectoryExists($directorio);
        $ruta = $directorio.'/creditos_fechas_vencimiento_'.now()->format('Ymd_His').'.csv';
        $archivo = fopen($ruta, 'wb');

        fputcsv($archivo, [
            'CreditoID', 'Codigo', 'Cliente', 'Sede', 'Estado', 'SaldoPendiente',
            'FechaGeneracion', 'NumeroCuotas', 'VencimientoGuardado',
            'VencimientoEsperado', 'DiasDiferencia', 'MorasRegistradas', 'MoraPagada',
            'MorasEliminadas', 'MontoMoraEliminado', 'NuevoTotalMora', 'Resultado',
        ], ';');

        foreach ($diferencias as $fila) {
            fputcsv($archivo, [
                $fila['credito_id'],
                $fila['codigo'],
                $fila['cliente'],
                $fila['sede'],
                $fila['estado'],
                number_format($fila['saldo_pendiente'], 2, '.', ''),
                $fila['fecha_generacion'],
                $fila['numero_cuotas'],
                $fila['vencimiento_guardado'],
                $fila['vencimiento_esperado'],
                $fila['dias_diferencia'],
                $fila['moras_registradas'],
                number_format($fila['mora_pagada'], 2, '.', ''),
                $fila['moras_eliminadas'],
                number_format($fila['monto_mora_eliminado'], 2, '.', ''),
                $fila['nuevo_total_mora'],
                $fila['resultado'],
            ], ';');
        }

        fclose($archivo);

        return $ruta;
    }

    private function rangoLimitadoA2026(): bool
    {
        return $this->option('desde') === '2026-01-01'
            && $this->option('hasta') === '2026-12-31';
    }

    private function generarRespaldoMoras(array $diferencias): string
    {
        $directorio = storage_path('app/auditorias');
        File::ensureDirectoryExists($directorio);
        $ruta = $directorio.'/respaldo_correccion_fechas_mora_'.now()->format('Ymd_His').'.json';
        $creditoIds = array_column($diferencias, 'credito_id');

        $creditos = DB::table('Credito')
            ->whereIn('CreditoID', $creditoIds)
            ->get(['CreditoID', 'FechaInicio', 'FechaVencimiento', 'EstatusCreditoFinal', 'SedeID']);

        $moras = DB::table('mora')
            ->whereIn('CreditoID', $creditoIds)
            ->orderBy('CreditoID')
            ->orderBy('FechaMora')
            ->orderBy('MoraID')
            ->get();

        File::put($ruta, json_encode([
            'generado_en' => now()->toDateTimeString(),
            'creditos' => $creditos,
            'moras' => $moras,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $ruta;
    }
}
