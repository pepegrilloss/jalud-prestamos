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
                'resultado' => 'PENDIENTE',
            ];
        }

        if ($this->option('fix')) {
            foreach ($diferencias as &$fila) {
                if ($fila['moras_registradas'] > 0) {
                    $fila['resultado'] = 'OMITIDO_CON_MORA';
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

                    DB::table('Credito')
                        ->where('CreditoID', $fila['credito_id'])
                        ->update([
                            'FechaInicio' => $fila['inicio_esperado'],
                            'FechaVencimiento' => $fila['vencimiento_esperado'],
                        ]);

                    AuditLog::registrar(
                        'CORREGIR_FECHA',
                        'Credito',
                        $fila['credito_id'],
                        [
                            'FechaInicio' => $creditoActual->FechaInicio,
                            'FechaVencimiento' => $creditoActual->FechaVencimiento,
                        ],
                        [
                            'FechaInicio' => $fila['inicio_esperado'],
                            'FechaVencimiento' => $fila['vencimiento_esperado'],
                            'Motivo' => 'Sincronizacion con cuotas laborables y calendario vigente',
                        ],
                        (int) $creditoActual->SedeID,
                        0
                    );

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
                'archivo_csv' => $rutaCsv,
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
        }

        if ($rutaCsv) {
            $this->line('CSV: '.$rutaCsv);
        }

        if ($diferencias !== []) {
            $this->table(
                ['Crédito', 'Sede', 'Generación', 'Cuotas', 'Guardado', 'Esperado', 'Días', 'Moras', 'Resultado'],
                array_map(fn (array $fila): array => [
                    $fila['codigo'],
                    $fila['sede'],
                    $fila['fecha_generacion'],
                    $fila['numero_cuotas'],
                    $fila['vencimiento_guardado'],
                    $fila['vencimiento_esperado'],
                    $fila['dias_diferencia'],
                    $fila['moras_registradas'],
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
            'VencimientoEsperado', 'DiasDiferencia', 'MorasRegistradas', 'Resultado',
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
                $fila['resultado'],
            ], ';');
        }

        fclose($archivo);

        return $ruta;
    }
}
