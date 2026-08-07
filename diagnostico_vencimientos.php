<?php
/**
 * DIAGNOSTICO: por que corregir_vencimientos_2026.php no encuentra creditos en PRD
 *
 * NO modifica nada. Solo reporta:
 *   1. Feriados de 2026 en feriados_peru (debe haber ~17, incl. 06-ago)
 *   2. Reglas de CalendarioNoMoroso (23-jul LABORABLE_FORZADO, 27-jul NO_LABORABLE)
 *   3. Histograma de diferencias BD vs calendario actual
 *   4. Logs previos (CORR_VENC / CORR_CUOTA) = ya se ejecuto algo antes?
 *   5. Creditos candidatos sin datos
 *
 * Ejecutar en PRD: php diagnostico_vencimientos.php
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

echo "============================================================\n";
echo "  DIAGNOSTICO VENCIMIENTOS 2026 (PRD)\n";
echo "============================================================\n";

// ─── 1. Feriados 2026 ───
echo "\n=== 1. FERIADOS_PERU 2026 ===\n";
try {
    $feriados = DB::table('feriados_peru')->whereYear('fecha', 2026)->orderBy('fecha')->get();
    echo "Total feriados 2026 en BD: " . count($feriados) . "\n";
    $tiene6ago = false;
    foreach ($feriados as $f) {
        $fechaStr = substr($f->fecha, 0, 10);
        if ($fechaStr === '2026-08-06') $tiene6ago = true;
        echo "  $fechaStr | " . ($f->nombre ?? '') . "\n";
    }
    echo ($tiene6ago ? "  >>> 06-ago (Batalla de Junin) PRESENTE <<<\n" : "  >>> FALTA el 06-ago (Batalla de Junin) <<<\n");
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// ─── 2. CalendarioNoMoroso ───
echo "\n=== 2. CALENDARIO NO MOROSO (reglas locales) ===\n";
try {
    $cols = DB::getSchemaBuilder()->getColumnListing('calendario_no_morosos');
    echo "Tabla: calendario_no_morosos | Columnas: " . implode(', ', $cols) . "\n";
    $reglas = DB::table('calendario_no_morosos')->orderBy('Fecha')->get();
    if (count($reglas) === 0) {
        echo "  SIN REGISTROS (la tabla existe pero vacia)\n";
    }
    foreach ($reglas as $r) {
        $line = [];
        foreach ($cols as $col) {
            $v = $r->$col ?? '';
            if ($v instanceof \DateTimeInterface || is_object($v)) $v = substr((string)$v, 0, 10);
            if ($v === '' || $v === null) continue;
            $line[] = "$col=$v";
        }
        echo "  " . implode(' | ', $line) . "\n";
    }
    $tiene23 = DB::table('calendario_no_morosos')->whereDate('Fecha', '2026-07-23')->where('Activo', 1)->exists();
    $tiene27 = DB::table('calendario_no_morosos')->whereDate('Fecha', '2026-07-27')->where('Activo', 1)->exists();
    echo ($tiene23 ? "  >>> 23-jul LABORABLE_FORZADO PRESENTE <<<\n" : "  >>> FALTA 23-jul LABORABLE_FORZADO <<<\n");
    echo ($tiene27 ? "  >>> 27-jul NO_LABORABLE PRESENTE <<<\n" : "  >>> FALTA 27-jul NO_LABORABLE <<<\n");
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// ─── 3. Histograma de diferencias ───
echo "\n=== 3. HISTOGRAMA DIFERENCIAS (candidatos 2026 activos saldo>0) ===\n";
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
    ->get();

echo "Candidatos: " . count($creditos) . "\n";

$buckets = ['<0 (adelantados)' => 0, '0 (correctos)' => 0, '+1' => 0, '+2' => 0, '+3' => 0, '+4 a +30' => 0, '> +30' => 0, 'sin datos' => 0];
$muestras = [];
$muestrasEspeciales = [];

foreach ($creditos as $c) {
    if (! $c->FechaVencimiento || ! $c->NumeroCuotas) { $buckets['sin datos']++; continue; }

    $seq = secuenciaCorrecta($c->FechaGeneracion, (int) $c->NumeroCuotas, (int) $c->SedeID);
    $vencEsperado = null;
    foreach ($seq as $f => $t) {
        if (str_starts_with($t, 'CUOTA_')) $vencEsperado = $f;
    }
    if (! $vencEsperado) { $buckets['sin datos']++; continue; }

    $actual = substr($c->FechaVencimiento, 0, 10);
    $dias = (int) (new Carbon($actual))->diffInDays(new Carbon($vencEsperado), false);

    if ($dias < 0) $buckets['<0 (adelantados)']++;
    elseif ($dias === 0) $buckets['0 (correctos)']++;
    elseif ($dias === 1) $buckets['+1']++;
    elseif ($dias === 2) $buckets['+2']++;
    elseif ($dias === 3) $buckets['+3']++;
    elseif ($dias <= 30) $buckets['+4 a +30']++;
    else $buckets['> +30']++;

    if (($dias < 1 || $dias > 30) && count($muestrasEspeciales) < 15) {
        $muestrasEspeciales[] = "{$c->CodigoCredito} | {$c->NumeroCuotas} cuotas | Gen=" . substr($c->FechaGeneracion,0,10) . " | Sede={$c->SedeID} | BD=$actual | Calc=$vencEsperado | diff=$dias";
    }
    if ($dias >= 1 && $dias <= 3 && count($muestras) < 8) {
        $muestras[] = "{$c->CodigoCredito} | {$c->NumeroCuotas} cuotas | Gen=" . substr($c->FechaGeneracion,0,10) . " | BD=$actual | Calc=$vencEsperado | +$dias";
    }
}

foreach ($buckets as $k => $v) echo "  $k: $v\n";

echo "\nMuestras de diferidos (para comparar):\n";
foreach ($muestras as $m) echo "  $m\n";

echo "\nMuestras de los NO clasificados (diff <1 o >30):\n";
foreach ($muestrasEspeciales as $m) echo "  $m\n";

// ─── 4. Logs previos ───
echo "\n=== 4. LOGS PREVIOS EN PRD ===\n";
foreach (['CORR_VENC', 'CORR_CUOTA', 'CORR_MORA', 'ELIM_CRED'] as $acc) {
    try {
        $n = DB::table('logs')->where('accion', $acc)->count();
        echo "  $acc: $n\n";
    } catch (\Exception $e) {
        echo "  $acc: ERROR " . $e->getMessage() . "\n";
    }
}

// ─── 5. Fechas sin datos ───
echo "\n=== 5. CANDIDATOS SIN FechaGeneracion o NumeroCuotas ===\n";
try {
    $sinGen = DB::table('Credito as c')
        ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
        ->where('c.Activo', 1)->where('pc.SaldoPendiente', '>', 0)
        ->whereYear('c.FechaGeneracion', 2026)
        ->where(function ($q) {
            $q->whereNull('c.FechaGeneracion')->orWhereNull('pc.NumeroCuotas')->orWhere('pc.NumeroCuotas', 0);
        })
        ->count();
    echo "  Sin gen o sin cuotas: $sinGen\n";
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n============================================================\n";
echo "  FIN DIAGNOSTICO\n";
echo "============================================================\n";
