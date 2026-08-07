<?php
/**
 * FIX cuotas #0 (DOMINGO/FERIADO) de los 619 creditos 2026.
 *
 * El script reparar_cuotas_2026.php reasigno las cuotas #0 por INDICE,
 * dejando Estados desalineados (ej: 06-ago y 29-jul como DOMINGO).
 *
 * Solucion: borrar todas las cuotas #0 DOMINGO/FERIADO (verificado:
 * ninguna tiene pagos asociados) y regenerarlas desde la secuencia
 * correcta (misma logica del CreditoObserver con calendario correcto).
 *
 * NO toca: PAGO_INICIAL, cuotas PAGADAS/PAGADO, cuotas #n>0.
 *
 * Ejecutar UNA SOLA VEZ: php fix_cuotas_cero_2026.php
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
echo "  FIX CUOTAS #0 DOMINGO/FERIADO (619 creditos 2026)\n";
echo "============================================================\n\n";

function secuenciaCorrecta($fechaGen, $numCuotas, $sedeId)
{
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

$logs = DB::table('logs')->where('accion', 'CORR_VENC')->select('modelo_id', 'SedeID')->get();
echo "Creditos: " . count($logs) . "\n";

$procesados = 0;
$conError = 0;
$borradas = 0;
$insertadas = 0;

foreach ($logs as $log) {
    $creditoId = (int) $log->modelo_id;

    $credito = DB::table('Credito as c')
        ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
        ->where('c.CreditoID', $creditoId)
        ->select('c.FechaGeneracion', 'c.SedeID', 'pc.NumeroCuotas')
        ->first();

    if (! $credito || ! $credito->NumeroCuotas) continue;

    $pdo->beginTransaction();
    try {
        // 1. Borrar cuotas #0 DOMINGO/FERIADO existentes (no hay pagos asociados - verificado)
        $del = DB::table('cuota')
            ->where('CreditoID', $creditoId)
            ->where('NumeroCuota', 0)
            ->whereIn('Estado', ['DOMINGO', 'FERIADO'])
            ->delete();
        $borradas += $del;

        // 2. Insertar cuotas #0 segun secuencia correcta
        $seq = secuenciaCorrecta($credito->FechaGeneracion, (int) $credito->NumeroCuotas, (int) $credito->SedeID);
        $nuevaFechaCreacion = now();
        foreach ($seq as $fecha => $tipo) {
            if ($tipo === 'DOMINGO' || $tipo === 'FERIADO') {
                DB::table('cuota')->insert([
                    'CreditoID' => $creditoId,
                    'NumeroCuota' => 0,
                    'FechaVencimiento' => $fecha,
                    'MontoCuota' => 0.00,
                    'Estado' => $tipo,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => $nuevaFechaCreacion,
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID,
                ]);
                $insertadas++;
            }
        }

        $pdo->commit();
        $procesados++;
    } catch (\Exception $e) {
        $pdo->rollBack();
        $conError++;
        echo "  [ERROR] CreditoID $creditoId: {$e->getMessage()}\n";
    }
}

echo "\n============================================================\n";
echo "  RESULTADO: $procesados creditos | $conError errores\n";
echo "  Cuotas #0 borradas: $borradas | insertadas: $insertadas\n";
echo "============================================================\n";
