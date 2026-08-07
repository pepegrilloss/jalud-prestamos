<?php
/**
 * CORREGIR VENCIMIENTOS DE CREDITOS SALDADOS 2026 (feriado 06-ago Junin)
 *
 * Complementa corregir_vencimientos_2026.php (que solo toca ACTIVOS con saldo).
 * Este script corrige SOLO:
 *   - EstatusCreditoFinal = SALDADO
 *   - FechaGeneracion en 2026
 *   - Rango que CRUZA el 06-ago (Batalla de Junin, faltante en Nager)
 *   - Diferencia de 1-3 dias
 *
 * SOLO actualiza Credito.FechaVencimiento (las cuotas NO se tocan:
 * son referenciales, segun lo indicado).
 *
 * VALIDACION PREVIA: verifica que el calendario tenga el 06-ago como feriado.
 * Si no, ABORTA (significa que feriados_peru no esta sincronizado y
 * el calculo seria incorrecto).
 *
 * IDEMPOTENTE. Ejecutar: php corregir_saldados_2026.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\CreditoFechaService;
use App\Services\CalendarioLaboralService;
use Carbon\Carbon;

foreach ([2025, 2026, 2027] as $a) {
    CalendarioLaboralService::esLaborable("{$a}-01-01", 1);
}

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR SALDADOS 2026 (feriado 06-ago Junin)\n";
echo "============================================================\n\n";

// ─── 0. VALIDACION del calendario ───
echo "=== VALIDACION DEL CALENDARIO ===\n";
try {
    $feriados = DB::table('feriados_peru')->whereYear('fecha', 2026)->count();
    $tiene6ago = DB::table('feriados_peru')->whereBetween('fecha', ['2026-08-06 00:00:00', '2026-08-06 23:59:59'])->exists();
    echo "Feriados 2026 en BD: $feriados\n";
    echo "06-ago (Batalla de Junin) presente: " . ($tiene6ago ? 'SI' : 'NO') . "\n";
    if (! $tiene6ago) {
        echo "\n[ABORTADO] El calendario no tiene el 06-ago como feriado.\n";
        echo "Ejecuta primero: php artisan feriados:sync 2026\n";
        exit(1);
    }
    echo "Calendario OK. Continuando...\n\n";
} catch (\Exception $e) {
    echo "\n[ABORTADO] No se puede leer feriados_peru: " . $e->getMessage() . "\n";
    echo "Ejecuta primero: php artisan migrate --path=database/migrations/2026_08_05_000002_create_feriados_peru_table.php\n";
    exit(1);
}

// ─── 1. Identificar candidatos saldados ───
$creditos = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('c.Activo', 1)
    ->where('pc.Activo', 1)
    ->where('pc.Estado', 'APROBADO')
    ->where('pc.FueRefinanciada', 0)
    ->where('pc.Eliminado', 0)
    ->where('c.EstatusCreditoFinal', 'SALDADO')
    ->whereYear('c.FechaGeneracion', 2026)
    ->whereDate('c.FechaVencimiento', '>=', '2026-08-06')
    ->select('c.CreditoID', 'pc.CodigoCredito', 'c.FechaGeneracion', 'c.FechaVencimiento',
             'pc.NumeroCuotas', 'c.SedeID')
    ->orderBy('c.CreditoID')
    ->get();

echo "Saldados 2026 con venc>=06-ago: " . count($creditos) . "\n\n";

// ─── 2. Clasificar ───
$aCorregir = [];
$yaCorrectos = 0;
$excluidos = 0;

foreach ($creditos as $c) {
    if (! $c->FechaVencimiento || ! $c->NumeroCuotas) continue;

    $rango = CreditoFechaService::calcularRangoPorCuotasLaborables($c->FechaGeneracion, (int) $c->NumeroCuotas, $c->SedeID);
    $esperado = $rango['FechaVencimiento']->format('Y-m-d');
    $actual = substr($c->FechaVencimiento, 0, 10);

    if ($actual === $esperado) { $yaCorrectos++; continue; }

    $dias = (int) (new Carbon($actual))->diffInDays(new Carbon($esperado), false);
    if ($dias < 1 || $dias > 30) continue;

    // Cruza el 06-ago?
    $inicio = Carbon::parse($c->FechaGeneracion)->addDay();
    $fin = Carbon::parse($actual);
    $cruza6ago = Carbon::parse('2026-08-06')->between($inicio, $fin);

    if ($cruza6ago && $dias >= 1 && $dias <= 3) {
        $aCorregir[] = [
            'credito_id' => (int) $c->CreditoID,
            'codigo' => $c->CodigoCredito,
            'sede' => (int) $c->SedeID,
            'actual' => $actual,
            'esperado' => $esperado,
            'dias' => $dias,
        ];
    } else {
        $excluidos++;
    }
}

echo "Ya correctos: $yaCorrectos\n";
echo "A CORREGIR (saldados, cruzan 06-ago, dif 1-3d): " . count($aCorregir) . "\n";
echo "EXCLUIDOS (feb-jun / especiales / no cruzan): $excluidos\n";

$porDias = [];
foreach ($aCorregir as $c) $porDias[$c['dias']] = ($porDias[$c['dias']] ?? 0) + 1;
ksort($porDias);
foreach ($porDias as $d => $n) echo "  +$d dia(s): $n creditos\n";

echo "\nLista completa:\n";
foreach ($aCorregir as $c) {
    echo "  {$c['codigo']} | Sede={$c['sede']} | {$c['actual']} -> {$c['esperado']} | +{$c['dias']}d\n";
}

if (count($aCorregir) === 0) {
    echo "\nNada que corregir.\n";
    exit(0);
}

echo "\nSe actualizara SOLO FechaVencimiento de " . count($aCorregir) . " creditos saldados (cuotas NO se tocan).\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── 3. Aplicar ───
$corregidos = 0;
$conError = 0;

foreach ($aCorregir as $c) {
    $pdo->beginTransaction();
    try {
        DB::table('Credito')->where('CreditoID', $c['credito_id'])
            ->update(['FechaVencimiento' => $c['esperado']]);

        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_SALD',
            'modelo' => 'Credito',
            'modelo_id' => $c['credito_id'],
            'old_values' => json_encode(['FechaVencimiento' => $c['actual']]),
            'new_values' => json_encode(['FechaVencimiento' => $c['esperado'], 'Motivo' => 'Feriado 06-ago (Batalla de Junin) faltante en Nager - saldado', 'Dias' => $c['dias']]),
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
echo "  RESULTADO: $corregidos corregidos | $conError errores\n";
echo "============================================================\n";
