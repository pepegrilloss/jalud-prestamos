<?php
/**
 * CORRIGE los 12 creditos con SaldoPendiente=0 pero EstatusCreditoFinal=ACTIVO.
 *
 * Bug: la sincronizacion de estatus (SaldoPendienteService) revirtio
 * SALDADO->ACTIVO en momentos transitorios (pagos retroactivos). Los creditos
 * quedaron con saldo 0 pero estatus ACTIVO y FechaSaldamiento vacio.
 *
 * Este script: para cada credito con saldo <= 0 y estatus != SALDADO,
 * verifica el saldo REAL (pagos activos - traslados) y si es 0 lo marca
 * como SALDADO con FechaSaldamiento = fecha del ultimo pago.
 *
 * SOLO procesa los 12 identificados (no barrido masivo).
 * IDEMPOTENTE. Ejecutar: php corregir_estados_saldados.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "============================================================\n";
echo "  CORREGIR ESTADOS SALDADO (12 creditos saldo 0 / ACTIVO)\n";
echo "============================================================\n\n";

$codigos = [
    'C-000369', 'C-003579', 'C-005702', 'C-005814', 'C-005945', 'C-005974',
    'C-005987', 'C-006215', 'C-006228', 'C-006235', 'C-006229', 'C-006269',
];

$aCorregir = [];
$yaOk = 0;

foreach ($codigos as $cod) {
    $credito = DB::table('Credito as c')
        ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'c.ProposicionCreditoID')
        ->where('pc.CodigoCredito', $cod)
        ->select('c.CreditoID', 'c.EstatusCreditoFinal', 'c.FechaSaldamiento', 'pc.MontoTotalPagar', 'pc.SaldoPendiente', 'c.SedeID')
        ->first();

    if (!$credito) {
        echo "  [SKIP] $cod no existe\n";
        continue;
    }

    // Saldo real: pagos activos no-mora - traslados aprobados
    $totalPagado = (float) DB::table('pago')
        ->where('CreditoID', $credito->CreditoID)
        ->where('Activo', 1)
        ->where('EsMora', 0)
        ->sum('MontoPagado');

    $traslados = (float) DB::table('solicitudes_resolucion_excedente as sre')
        ->join('pago as p2', 'sre.PagoOrigenID', '=', 'p2.PagoID')
        ->where('p2.CreditoID', $credito->CreditoID)
        ->where('sre.TipoResolucion', 'TRASLADO_DE_PAGO')
        ->where('sre.Estado', 'APROBADA')
        ->sum('sre.MontoAplicar');

    $saldoReal = max(0, (float) $credito->MontoTotalPagar - ($totalPagado - $traslados));

    $fechaUltimoPago = DB::table('pago')
        ->where('CreditoID', $credito->CreditoID)
        ->where('Activo', 1)
        ->where('EsMora', 0)
        ->max('FechaPago');

    if ($credito->EstatusCreditoFinal === 'SALDADO') {
        $yaOk++;
        continue;
    }

    if ($saldoReal <= 0.009) {
        $aCorregir[] = [
            'credito_id' => (int) $credito->CreditoID,
            'codigo' => $cod,
            'sede' => (int) $credito->SedeID,
            'saldo_real' => $saldoReal,
            'fecha_ultimo_pago' => $fechaUltimoPago,
        ];
    } else {
        echo "  [ERROR] $cod tiene saldo REAL de S/{$saldoReal} — NO se marca SALDADO. Revise manualmente.\n";
    }
}

echo "Ya SALDADO correctamente: $yaOk\n";
echo "A CORREGIR: " . count($aCorregir) . "\n\n";
foreach ($aCorregir as $c) {
    echo "  {$c['codigo']} | CreditoID={$c['credito_id']} | Saldo real=0 | Ultimo pago={$c['fecha_ultimo_pago']}\n";
}

if (count($aCorregir) === 0) {
    echo "\nNada que corregir.\n";
    exit(0);
}

echo "\nSe marcaran " . count($aCorregir) . " creditos como SALDADO.\n";
echo "Presiona ENTER para continuar o CTRL+C para cancelar...";
fgets(STDIN);

// ─── Aplicar ───
$corregidos = 0;
$conError = 0;

foreach ($aCorregir as $c) {
    $pdo->beginTransaction();
    try {
        $old = DB::table('Credito')->where('CreditoID', $c['credito_id'])->first(['EstatusCreditoFinal', 'FechaSaldamiento']);

        DB::table('Credito')->where('CreditoID', $c['credito_id'])
            ->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => $c['fecha_ultimo_pago'] ?: now(),
            ]);

        DB::table('logs')->insert([
            'user_id' => 0,
            'accion' => 'CORR_ESTADO',
            'modelo' => 'Credito',
            'modelo_id' => $c['credito_id'],
            'old_values' => json_encode(['EstatusCreditoFinal' => $old->EstatusCreditoFinal, 'FechaSaldamiento' => $old->FechaSaldamiento]),
            'new_values' => json_encode(['EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => $c['fecha_ultimo_pago'] ?: now(), 'Motivo' => 'Saldo 0 real, estatus ACTIVO por bug de sincronizacion']),
            'created_at' => now(),
            'SedeID' => $c['sede'],
        ]);

        $pdo->commit();
        $corregidos++;
        echo "  [OK] {$c['codigo']} -> SALDADO\n";
    } catch (\Exception $e) {
        $pdo->rollBack();
        $conError++;
        echo "  [ERROR] {$c['codigo']}: {$e->getMessage()}\n";
    }
}

echo "\n============================================================\n";
echo "  RESULTADO: $corregidos corregidos | $conError errores\n";
echo "============================================================\n";
