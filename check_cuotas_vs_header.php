<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Credito;

$discrepancies = [];
Credito::with('proposicion')->get()->each(function ($c) use (&$discrepancies) {
    if (!$c->proposicion) return;

    $montoTotalPagar = (float)$c->proposicion->MontoTotalPagar;
    $sumCuotas = (float)$c->cuotas()->where('Activo', true)->sum('MontoCuota');
    
    if (abs($montoTotalPagar - $sumCuotas) > 1.0) {
        $discrepancies[] = [
            'ID' => $c->proposicion->ProposicionCreditoID,
            'Codigo' => $c->proposicion->CodigoCredito,
            'MontoHeader' => $montoTotalPagar,
            'SumaCuotas' => $sumCuotas,
            'Diferencia' => $montoTotalPagar - $sumCuotas,
            'Estado' => $c->proposicion->Estado
        ];
    }
});

if (empty($discrepancies)) {
    echo "No se encontraron discrepancias entre cabecera y cuotas.\n";
} else {
    echo json_encode($discrepancies, JSON_PRETTY_PRINT) . "\n";
}
