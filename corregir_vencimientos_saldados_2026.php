<?php
/**
 * CORREGIR VENCIMIENTOS DE CREDITOS SALDADOS (generados desde 2026-03-01)
 *
 * Corrige SOLO Credito.FechaVencimiento de creditos SALDADOS cuyo vencimiento
 * no coincide con la fecha de la cuota #NumeroCuotas segun el calendario
 * laboral actual (lunes-sabado, sin domingos ni feriados nacionales, con las
 * reglas locales de calendario_no_morosos por sede).
 *
 * NO toca: cuotas (PAGADAS), pagos, saldos, mora, ni creditos activos.
 *
 * Uso:
 *   php corregir_vencimientos_saldados_2026.php              -> MODO REPORTE (no escribe nada)
 *   php corregir_vencimientos_saldados_2026.php --aplicar     -> Aplica correcciones con log
 *   php corregir_vencimientos_saldados_2026.php --aplicar 2026-03-01   -> Fecha desde personalizada
 *
 * IDEMPOTENTE: puede ejecutarse 2 veces sin danar datos.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\CreditoFechaService;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$aplicar = in_array('--aplicar', $argv, true);
$desde = null;
foreach ($argv as $arg) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
        $desde = $arg;
    }
}
$desde = $desde ?? '2026-03-01';

echo "============================================================\n";
echo "  CORREGIR VENCIMIENTOS SALDADOS (desde {$desde})\n";
echo "  Modo: " . ($aplicar ? 'APLICAR (escribe en BD)' : 'REPORTE (solo lectura)') . "\n";
echo "============================================================\n\n";

// 1. Candidatos: creditos SALDADOS activos con proposicion activa
$creditos = DB::table('Credito as c')
    ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
    ->where('c.Activo', 1)
    ->where('pc.Activo', 1)
    ->where('c.EstatusCreditoFinal', 'SALDADO')
    ->whereDate('c.FechaGeneracion', '>=', $desde)
    ->select(
        'c.CreditoID',
        'pc.CodigoCredito',
        'c.FechaGeneracion',
        'c.FechaVencimiento',
        'pc.NumeroCuotas',
        'c.SedeID'
    )
    ->orderBy('c.FechaGeneracion')
    ->get();

echo "Candidatos saldados desde {$desde}: " . count($creditos) . "\n\n";

// 2. Clasificar
$aCorregir = [];
$excluidos = [];
$yaCorrectos = 0;
$sinDatos = 0;

foreach ($creditos as $c) {
    if (! $c->FechaVencimiento || ! $c->NumeroCuotas) {
        $sinDatos++;
        continue;
    }

    try {
        $esperado = CreditoFechaService::calcularRangoPorCuotasLaborables(
            $c->FechaGeneracion,
            (int) $c->NumeroCuotas,
            (int) $c->SedeID
        )['FechaVencimiento']->format('Y-m-d');
    } catch (\Throwable $e) {
        $sinDatos++;
        continue;
    }

    $actual = substr($c->FechaVencimiento, 0, 10);

    if ($actual === $esperado) {
        $yaCorrectos++;
        continue;
    }

    $dias = (int) (new \Carbon\Carbon($actual))->diffInDays(new \Carbon\Carbon($esperado), false);

    // Salvaguarda: diferencias extremas indican datos corruptos (ej. NumeroCuotas
    // alterado). No se tocan automaticamente.
    if (abs($dias) > 60) {
        $excluidos[] = [
            'codigo' => $c->CodigoCredito,
            'actual' => $actual,
            'esperado' => $esperado,
            'dias' => $dias,
        ];
        continue;
    }

    $aCorregir[] = [
        'credito_id' => (int) $c->CreditoID,
        'codigo' => $c->CodigoCredito,
        'sede' => (int) $c->SedeID,
        'gen' => substr($c->FechaGeneracion, 0, 10),
        'cuotas' => (int) $c->NumeroCuotas,
        'actual' => $actual,
        'esperado' => $esperado,
        'dias' => $dias,
    ];
}

echo "Ya correctos: {$yaCorrectos}\n";
echo "A CORREGIR: " . count($aCorregir) . "\n";
echo "Sin datos (salteados): {$sinDatos}\n";
echo "EXCLUIDOS (outlier >60 dias, revisar a mano): " . count($excluidos ?? []) . "\n";
foreach ($excluidos ?? [] as $x) {
    echo "  {$x['codigo']} | {$x['actual']} -> {$x['esperado']} | {$x['dias']}d\n";
}
echo "\n";

if (count($aCorregir) === 0) {
    echo "Nada que corregir.\n";
    exit(0);
}

$porDias = [];
foreach ($aCorregir as $c) {
    $porDias[$c['dias']] = ($porDias[$c['dias']] ?? 0) + 1;
}
ksort($porDias);
echo "Distribucion de la diferencia (dias, actual vs esperado):\n";
foreach ($porDias as $d => $n) {
    echo "  {$d} dia(s): {$n} creditos\n";
}

echo "\nMuestra (primeros 15):\n";
foreach (array_slice($aCorregir, 0, 15) as $c) {
    echo "  {$c['codigo']} | Sede={$c['sede']} | Gen={$c['gen']} | {$c['cuotas']} cuotas | {$c['actual']} -> {$c['esperado']} | {$c['dias']}d\n";
}

if (! $aplicar) {
    echo "\nMODO REPORTE: no se escribio nada.\n";
    echo "Para aplicar: php corregir_vencimientos_saldados_2026.php --aplicar\n";
    exit(0);
}

echo "\nAplicando correccion a " . count($aCorregir) . " creditos...\n";

// 3. Aplicar
$corregidos = 0;
$conError = 0;

foreach ($aCorregir as $c) {
    $pdo->beginTransaction();
    try {
        DB::table('Credito')
            ->where('CreditoID', $c['credito_id'])
            ->update(['FechaVencimiento' => $c['esperado']]);

        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_VENC_SALDADO',
            'modelo' => 'Credito',
            'modelo_id' => $c['credito_id'],
            'old_values' => json_encode(['FechaVencimiento' => $c['actual']]),
            'new_values' => json_encode([
                'FechaVencimiento' => $c['esperado'],
                'Motivo' => 'Recalculo con calendario laboral actual (domingos/feriados/reglas locales)',
                'Dias' => $c['dias'],
                'NumeroCuotas' => $c['cuotas'],
            ]),
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
echo "  RESULTADO: {$corregidos} corregidos | {$conError} errores\n";
echo "============================================================\n";
