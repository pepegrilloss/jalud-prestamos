<?php
/**
 * CORREGIR VENCIMIENTOS 2026 (feriado 06-ago Batalla de Junin faltante en Nager)
 *
 * UN SOLO SCRIPT que hace todo:
 *   1. Corrige FechaVencimiento del credito (misma logica que CreditoFechaService)
 *   2. Regenera las cuotas NO PAGADAS con la MISMA logica del CreditoObserver
 *      (dia a dia desde FechaGeneracion+1 con calendario correcto)
 *   3. Regenera cuotas #0 DOMINGO/FERIADO de control
 *   4. NO toca cuotas PAGADAS/PAGADO (historico) ni PAGO_INICIAL
 *
 * Regla de negocio (verificada): lunes-sabado laborables, domingos no,
 * 23-jul laborable forzado, 27-28-29-jul y 06-ago excluidos.
 * SOLO creditos 2026 ACTIVOS con saldo>0, no refinanciados, cuyo rango
 * cruza el 06-ago y diferencia de 1-3 dias. NO toca feb-jun ni especiales.
 *
 * IDEMPOTENTE: puede ejecutarse 2 veces sin danar datos.
 *
 * Ejecutar: php corregir_vencimientos_2026.php
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
echo "  CORREGIR VENCIMIENTOS 2026 (feriado 06-ago Junin)\n";
echo "============================================================\n\n";

// ─── Secuencia correcta (logica del CreditoObserver) ───
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

// ─── 1. Identificar candidatos ───
$creditos = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('c.Activo', 1)
    ->where('pc.Activo', 1)
    ->where('pc.Estado', 'APROBADO')
    ->where('pc.FueRefinanciada', 0)
    ->where('pc.Eliminado', 0)
    ->where('pc.SaldoPendiente', '>', 0)
    ->where('c.EstatusCreditoFinal', 'ACTIVO')
    ->whereYear('c.FechaGeneracion', 2026)
    ->whereDate('c.FechaVencimiento', '>=', '2026-08-06')
    ->select('c.CreditoID', 'pc.CodigoCredito', 'c.FechaGeneracion', 'c.FechaVencimiento',
             'pc.NumeroCuotas', 'c.SedeID')
    ->orderBy('c.CreditoID')
    ->get();

echo "Candidatos (2026, activos, saldo>0, venc>=06-ago): " . count($creditos) . "\n\n";

// ─── 2. Clasificar ───
$aCorregir = [];
$excluidos = [];
$yaCorrectos = 0;

foreach ($creditos as $c) {
    if (! $c->FechaVencimiento || ! $c->NumeroCuotas) continue;

    $seq = secuenciaCorrecta($c->FechaGeneracion, (int) $c->NumeroCuotas, (int) $c->SedeID);
    $esperadoCuotaN = [];
    $vencimientoEsperado = null;
    foreach ($seq as $f => $t) {
        if (str_starts_with($t, 'CUOTA_')) {
            $n = (int) substr($t, 6);
            $esperadoCuotaN[$n] = $f;
            $vencimientoEsperado = $f;
        }
    }

    $actual = substr($c->FechaVencimiento, 0, 10);
    if ($actual === $vencimientoEsperado) { $yaCorrectos++; continue; }

    $dias = (int) (new Carbon($actual))->diffInDays(new Carbon($vencimientoEsperado), false);
    if ($dias < 1 || $dias > 30) continue;

    // Cruza el 06-ago? (comparar solo fechas, sin horas)
    $inicio = Carbon::parse($c->FechaGeneracion)->addDay()->startOfDay();
    $fin = Carbon::parse($actual)->startOfDay();
    $cruza6ago = Carbon::parse('2026-08-06')->startOfDay()->between($inicio, $fin);

    if ($cruza6ago && $dias >= 1 && $dias <= 3) {
        $aCorregir[] = [
            'credito_id' => (int) $c->CreditoID,
            'codigo' => $c->CodigoCredito,
            'sede' => (int) $c->SedeID,
            'gen' => substr($c->FechaGeneracion, 0, 10),
            'cuotas' => (int) $c->NumeroCuotas,
            'actual' => $actual,
            'esperado' => $vencimientoEsperado,
            'dias' => $dias,
        ];
    } else {
        $excluidos[] = $c->CodigoCredito;
    }
}

echo "Ya correctos: $yaCorrectos\n";
echo "A CORREGIR (cruzan 06-ago, dif 1-3d): " . count($aCorregir) . "\n";
echo "EXCLUIDOS (feb-jun / especiales / no cruzan): " . count($excluidos) . "\n";

$porDias = [];
foreach ($aCorregir as $c) $porDias[$c['dias']] = ($porDias[$c['dias']] ?? 0) + 1;
ksort($porDias);
foreach ($porDias as $d => $n) echo "  +$d dia(s): $n creditos\n";

echo "\nMuestra (primeros 10):\n";
foreach (array_slice($aCorregir, 0, 10) as $c) {
    echo "  {$c['codigo']} | Sede={$c['sede']} | Gen={$c['gen']} | {$c['cuotas']} cuotas | {$c['actual']} -> {$c['esperado']} | +{$c['dias']}d\n";
}

if (count($aCorregir) === 0) {
    echo "\nNada que corregir (los datos ya estan correctos).\n";
    exit(0);
}

echo "\nSe corregiran " . count($aCorregir) . " creditos (vencimiento + cuotas pendientes + dias de control).\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 3. Aplicar ───
$corregidos = 0;
$conError = 0;
$insertadas = 0;

foreach ($aCorregir as $c) {
    $pdo->beginTransaction();
    try {
        $seq = secuenciaCorrecta($c['gen'], $c['cuotas'], $c['sede']);

        $fechasCuotaN = [];
        $fechasNoLab = [];
        foreach ($seq as $f => $t) {
            if (str_starts_with($t, 'CUOTA_')) {
                $fechasCuotaN[(int) substr($t, 6)] = $f;
            } else {
                $fechasNoLab[] = ['fecha' => $f, 'tipo' => $t];
            }
        }

        // 3a. FechaVencimiento del credito
        DB::table('Credito')->where('CreditoID', $c['credito_id'])
            ->update(['FechaVencimiento' => $c['esperado']]);

        // 3b. Cuotas NO PAGADAS #n: fecha correcta segun su numero
        $cuotas = DB::table('cuota')
            ->where('CreditoID', $c['credito_id'])
            ->where('Activo', 1)
            ->whereNotIn('Estado', ['PAGADA', 'PAGADO', 'PAGO_INICIAL'])
            ->get();

        foreach ($cuotas as $cuota) {
            $n = (int) $cuota->NumeroCuota;
            if ($n > 0 && isset($fechasCuotaN[$n])) {
                DB::table('cuota')->where('CuotaID', $cuota->CuotaID)
                    ->update(['FechaVencimiento' => $fechasCuotaN[$n]]);
            }
        }

        // 3c. Regenerar cuotas #0 DOMINGO/FERIADO (verificado: sin pagos asociados)
        DB::table('cuota')
            ->where('CreditoID', $c['credito_id'])
            ->where('NumeroCuota', 0)
            ->whereIn('Estado', ['DOMINGO', 'FERIADO'])
            ->delete();

        $fechaCreacion = now();
        foreach ($fechasNoLab as $nl) {
            DB::table('cuota')->insert([
                'CreditoID' => $c['credito_id'],
                'NumeroCuota' => 0,
                'FechaVencimiento' => $nl['fecha'],
                'MontoCuota' => 0.00,
                'Estado' => $nl['tipo'],
                'DiasAtraso' => 0,
                'MontoMora' => 0.00,
                'FechaPago' => null,
                'FechaCreacion' => $fechaCreacion,
                'FechaModificacion' => null,
                'Activo' => 1,
                'SedeID' => $c['sede'],
            ]);
            $insertadas++;
        }

        // 3d. Log
        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_VENC',
            'modelo' => 'Credito',
            'modelo_id' => $c['credito_id'],
            'old_values' => json_encode(['FechaVencimiento' => $c['actual']]),
            'new_values' => json_encode(['FechaVencimiento' => $c['esperado'], 'Motivo' => 'Feriado 06-ago (Batalla de Junin) faltante en Nager', 'Dias' => $c['dias']]),
            'created_at' => now(),
            'SedeID' => $c['sede'],
        ]);

        $pdo->commit();
        $corregidos++;
    } catch (\Exception $e) {
        $pdo->rollBack();
        $conError++;
        echo "  [ERROR] {$c['codigo']}: {$e->getMessage()}\n";
    }
}

echo "\n============================================================\n";
echo "  RESULTADO: $corregidos corregidos | $conError errores | $insertadas cuotas de control regeneradas\n";
echo "============================================================\n";
