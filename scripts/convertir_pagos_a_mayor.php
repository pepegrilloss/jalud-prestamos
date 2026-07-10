<?php
/**
 * Convierte los pagos del credito C-003603 del 12/06/2026 al 22/06/2026 a "pago a mayor".
 * Solo ejecutar una vez.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$codigo = 'C-003603';
$desde  = '2026-06-12';
$hasta  = '2026-06-22';

$creditoId = \App\Models\ProposicionCredito::withoutGlobalScopes()
    ->where('CodigoCredito', $codigo)
    ->join('Credito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
    ->value('Credito.CreditoID');

if (!$creditoId) {
    die("ERROR: No se encontro el credito $codigo\n");
}

echo "Credito: $codigo (CreditoID: $creditoId)\n";
echo "Rango: $desde al $hasta\n\n";

echo "--- ANTES ---\n";
$pagos = \App\Models\Pago::withoutGlobalScopes()
    ->where('CreditoID', $creditoId)
    ->where('FechaPago', '>=', $desde)
    ->where('FechaPago', '<', $hasta . ' 23:59:59')
    ->orderBy('FechaPago')
    ->get();

foreach ($pagos as $p) {
    echo "  PagoID={$p->PagoID} | {$p->FechaPago->format('Y-m-d')} | Monto={$p->MontoPagado} | Concepto={$p->TipoConcepto} | A_MAYOR={$p->EsPagoAMayor}\n";
}

$afectados = \App\Models\Pago::withoutGlobalScopes()
    ->where('CreditoID', $creditoId)
    ->where('FechaPago', '>=', $desde)
    ->where('FechaPago', '<', $hasta . ' 23:59:59')
    ->update([
        'TipoConcepto' => 'C',
        'EsPagoAMayor' => 1,
        'EsPagoForzado' => 0,
    ]);

echo "\nActualizados: $afectados pagos\n\n";

echo "--- DESPUES ---\n";
$pagos = \App\Models\Pago::withoutGlobalScopes()
    ->where('CreditoID', $creditoId)
    ->where('FechaPago', '>=', $desde)
    ->where('FechaPago', '<', $hasta . ' 23:59:59')
    ->orderBy('FechaPago')
    ->get();

foreach ($pagos as $p) {
    echo "  PagoID={$p->PagoID} | {$p->FechaPago->format('Y-m-d')} | Monto={$p->MontoPagado} | Concepto={$p->TipoConcepto} | A_MAYOR={$p->EsPagoAMayor}\n";
}

echo "\nListo.\n";
