<?php
/**
 * Eliminar credito C-000397 (soft-delete con trazabilidad)
 * Ejecutar: php eliminar_credito_397.php
 */
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$codigo = 'C-000397';

$prop = DB::table('ProposicionCredito')->where('CodigoCredito', $codigo)->first();
if (!$prop) { echo "{$codigo} no encontrado\n"; exit; }

echo "=== ELIMINAR {$codigo} ===\n";
echo "  Cliente: " . DB::table('Cliente')->where('ClienteID', $prop->ClienteID)->value('NombresApellidos') . "\n";
echo "  SedeID: {$prop->SedeID}\n\n";

DB::transaction(function () use ($prop) {
    $credito = DB::table('Credito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->first();
    $creditoID = $credito->CreditoID ?? null;

    // Marcar proposicion como eliminada
    DB::table('ProposicionCredito')->where('ProposicionCreditoID', $prop->ProposicionCreditoID)->update([
        'Eliminado' => 1,
        'FechaEliminacion' => now(),
        'UserEliminacionID' => auth()->id() ?? 0,
        'MotivoEliminacion' => 'Eliminacion manual por solicitud',
        'Activo' => 0,
        'SaldoPendiente' => 0,
    ]);

    if ($creditoID) {
        DB::table('Credito')->where('CreditoID', $creditoID)->update([
            'Activo' => 0,
            'EstatusCreditoFinal' => 'ELIMINADO',
            'FechaSaldamiento' => now(),
        ]);
        DB::table('pago')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
        DB::table('cuota')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
        DB::table('mora')->where('CreditoID', $creditoID)->delete();
    }

    echo "  {$prop->CodigoCredito} eliminado (soft-delete)\n";
});

echo "\nDone.\n";
