<?php
/**
 * REPARACION de cuotas de los 619 creditos corregidos por corregir_vencimientos_2026.php
 *
 * Problema del script anterior: corrio TODAS las cuotas no pagadas +N dias,
 * moviendo tambien las cuotas ANTERIORES al 06-ago (que ya estaban correctas)
 * y las de control (DOMINGO/FERIADO).
 *
 * Este script:
 *   1. Revierte el corrimiento ciego (resta N dias a las no pagadas).
 *   2. Regenera las fechas de las cuotas NO PAGADAS con la MISMA logica del
 *      CreditoObserver (dia a dia desde FechaGeneracion+1) usando el
 *      calendario CORRECTO (Calendarific + reglas locales).
 *   3. Inserta las cuotas #0 FERIADO/DOMINGO que falten (ej: 06-ago).
 *   4. NO toca cuotas PAGADAS/PAGADO (historico) ni PAGO_INICIAL.
 *
 * Ejecutar UNA SOLA VEZ: php reparar_cuotas_2026.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\CalendarioLaboralService;
use Carbon\Carbon;

foreach ([2025, 2026, 2027] as $a) {
    CalendarioLaboralService::esLaborable("{$a}-01-01", 1);
}

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  REPARAR CUOTAS 2026 (regeneracion con logica del observer)\n";
echo "============================================================\n\n";

// ─── 1. Leer logs CORR_VENC ───
$logs = DB::table('logs')
    ->where('accion', 'CORR_VENC')
    ->select('modelo_id', 'new_values', 'SedeID')
    ->get();

echo "Creditos en log CORR_VENC: " . count($logs) . "\n\n";

// ─── 2. Función: construir secuencia correcta (lógica del CreditoObserver) ───
function secuenciaCorrecta($fechaGen, $numCuotas, $sedeId)
{
    // Retorna [fecha => tipo] donde tipo = 'CUOTA_n' | 'DOMINGO' | 'FERIADO'
    $seq = [];
    $fecha = Carbon::parse($fechaGen)->addDay();
    $n = 0;
    while ($n < $numCuotas) {
        $fechaStr = $fecha->format('Y-m-d');
        if (! CalendarioLaboralService::esLaborable($fecha, $sedeId)) {
            $seq[$fechaStr] = $fecha->dayOfWeek === Carbon::SUNDAY ? 'DOMINGO' : 'FERIADO';
        } else {
            $n++;
            $seq[$fechaStr] = "CUOTA_$n";
        }
        $fecha->addDay();
    }
    return $seq;
}

$corregidos = 0;
$conError = 0;
$insertadosFeriados = 0;

foreach ($logs as $log) {
    $creditoId = (int) $log->modelo_id;
    $newVals = json_decode($log->new_values, true);
    $dias = (int) ($newVals['Dias'] ?? 1);

    $credito = DB::table('Credito as c')
        ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
        ->where('c.CreditoID', $creditoId)
        ->select('c.CreditoID', 'c.FechaGeneracion', 'c.SedeID', 'pc.NumeroCuotas', 'pc.CodigoCredito')
        ->first();

    if (! $credito || ! $credito->NumeroCuotas) {
        echo "  [SKIP] CreditoID $creditoId no encontrado\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        // ─── a. Revertir corrimiento ciego ───
        DB::table('cuota')
            ->where('CreditoID', $creditoId)
            ->whereNotIn('Estado', ['PAGADA', 'PAGADO', 'PAGO_INICIAL'])
            ->update(['FechaVencimiento' => DB::raw("DATE_SUB(FechaVencimiento, INTERVAL $dias DAY)")]);

        // ─── b. Secuencia correcta ───
        $seq = secuenciaCorrecta($credito->FechaGeneracion, (int) $credito->NumeroCuotas, (int) $credito->SedeID);

        // Fechas de cuotas #n (habiles) en la secuencia correcta
        $fechasCuotaN = [];
        foreach ($seq as $fecha => $tipo) {
            if (str_starts_with($tipo, 'CUOTA_')) {
                $fechasCuotaN[(int) substr($tipo, 6)] = $fecha;
            }
        }

        // Fechas de dias no laborables en la secuencia correcta (en orden)
        $fechasNoLaborables = [];
        foreach ($seq as $fecha => $tipo) {
            if ($tipo === 'DOMINGO' || $tipo === 'FERIADO') {
                $fechasNoLaborables[] = ['fecha' => $fecha, 'tipo' => $tipo];
            }
        }

        // ─── c. Reasignar cuotas NO PAGADAS ───
        $cuotas = DB::table('cuota')
            ->where('CreditoID', $creditoId)
            ->where('Activo', 1)
            ->whereNotIn('Estado', ['PAGADA', 'PAGADO', 'PAGO_INICIAL'])
            ->orderBy('NumeroCuota')
            ->orderBy('FechaVencimiento')
            ->get();

        $cuotasNoLab = [];
        foreach ($cuotas as $cuota) {
            $n = (int) $cuota->NumeroCuota;
            if ($n > 0) {
                if (isset($fechasCuotaN[$n])) {
                    DB::table('cuota')->where('CuotaID', $cuota->CuotaID)
                        ->update(['FechaVencimiento' => $fechasCuotaN[$n]]);
                }
            } else {
                $cuotasNoLab[] = $cuota;
            }
        }

        // Reasignar #0 (DOMINGO/FERIADO) en orden cronologico
        $idx = 0;
        foreach ($cuotasNoLab as $cuota) {
            if ($idx < count($fechasNoLaborables)) {
                DB::table('cuota')->where('CuotaID', $cuota->CuotaID)
                    ->update(['FechaVencimiento' => $fechasNoLaborables[$idx]['fecha']]);
                $idx++;
            }
        }

        // ─── d. Insertar cuotas #0 faltantes (ej: 06-ago FERIADO) ───
        $fechasExistentes = DB::table('cuota')
            ->where('CreditoID', $creditoId)
            ->where('Activo', 1)
            ->pluck('FechaVencimiento')
            ->map(fn ($f) => substr($f, 0, 10))
            ->flip();

        for (; $idx < count($fechasNoLaborables); $idx++) {
            $nl = $fechasNoLaborables[$idx];
            if (! isset($fechasExistentes[$nl['fecha']])) {
                DB::table('cuota')->insert([
                    'CreditoID' => $creditoId,
                    'NumeroCuota' => 0,
                    'FechaVencimiento' => $nl['fecha'],
                    'MontoCuota' => 0.00,
                    'Estado' => $nl['tipo'],
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => now(),
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID,
                ]);
                $insertadosFeriados++;
            }
        }

        // ─── e. Log ───
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_CUOTA',
            'modelo' => 'Credito',
            'modelo_id' => $creditoId,
            'old_values' => json_encode(['Tipo' => 'Regeneracion cuotas no pagadas (post CORR_VENC)']),
            'new_values' => json_encode(['CuotasRegeneradas' => count($cuotas), 'DiasRevertidos' => $dias]),
            'created_at' => now(),
            'SedeID' => $credito->SedeID,
        ]);

        $pdo->commit();
        $corregidos++;
    } catch (\Exception $e) {
        $pdo->rollBack();
        $conError++;
        echo "  [ERROR] CreditoID $creditoId ({$credito->CodigoCredito}): {$e->getMessage()}\n";
    }
}

echo "\n============================================================\n";
echo "  RESULTADO: $corregidos regenerados | $conError errores | $insertadosFeriados cuotas #0 insertadas\n";
echo "============================================================\n";
