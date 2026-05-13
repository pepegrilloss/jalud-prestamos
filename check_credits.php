<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProposicionCredito;

$discrepancies = [];
// Revisamos todos los registros, no solo los activos
ProposicionCredito::all()->each(function ($p) use (&$discrepancies) {
    $montoTotal = (float)$p->MontoTotal;
    $montoInteres = (float)$p->MontoInteres;
    $montoTotalPagar = (float)$p->MontoTotalPagar;
    $tasaInteres = (float)$p->TasaInteres;
    $numeroCuotas = (int)$p->NumeroCuotas;
    $montoCuota = (float)$p->MontoCuota;

    $expectedInteres = round($montoTotal * ($tasaInteres / 100), 2);
    $expectedTotal = round($montoTotal + $montoInteres, 2);
    
    $hasError = false;
    $errors = [];
    
    // 1. Validar Interés vs (Monto * Tasa)
    if (abs($montoInteres - $expectedInteres) > 0.05) {
        $hasError = true;
        $errors[] = "Interés vs Tasa: esperado {$expectedInteres}, real {$montoInteres}";
    }
    
    // 2. Validar Total vs (Monto + Interés)
    if (abs($montoTotalPagar - $expectedTotal) > 0.05) {
        $hasError = true;
        $errors[] = "Total vs Suma: esperado {$expectedTotal}, real {$montoTotalPagar}";
    }
    
    // 3. Validar Total vs (Cuotas * MontoCuota)
    if ($numeroCuotas > 0) {
        $expectedTotalFromCuotas = round($numeroCuotas * $montoCuota, 2);
        if (abs($montoTotalPagar - $expectedTotalFromCuotas) > 1.0) { // Tolerancia de 1 sol
             $hasError = true;
             $errors[] = "Total vs Cuotas: esperado {$expectedTotalFromCuotas} ({$numeroCuotas} * {$montoCuota}), real {$montoTotalPagar}";
        }
    }

    if ($hasError) {
        $discrepancies[] = [
            'ID' => $p->ProposicionCreditoID,
            'Codigo' => $p->CodigoCredito,
            'Monto' => $montoTotal,
            'Tasa' => $tasaInteres,
            'Interes' => $montoInteres,
            'Total' => $montoTotalPagar,
            'Cuotas' => $numeroCuotas,
            'MontoCuota' => $montoCuota,
            'Estado' => $p->Estado,
            'Activo' => $p->Activo,
            'Errores' => $errors
        ];
    }
});

if (empty($discrepancies)) {
    echo "No se encontraron discrepancias en ningún crédito.\n";
} else {
    echo json_encode($discrepancies, JSON_PRETTY_PRINT) . "\n";
}
