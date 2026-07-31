<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fecha = '2026-07-15';
$p = \App\Models\PromotorCobrador::where('Descripcion', 'LIKE', '%CURO%TICLLA%')->first();
$zonaId = $p->ZonaID;

echo "Promotores en Zona {$zonaId}:\n";
$promotoresZona = \App\Models\PromotorCobrador::withoutGlobalScopes()
    ->where('ZonaID', $zonaId)
    ->where('Activo', true)
    ->get();
foreach ($promotoresZona as $pr) {
    echo "ID: {$pr->PromotorCobradorID} | Nombre: {$pr->Descripcion}\n";
}

// Check details of the 50 and 5 payments
$pagos = \Illuminate\Support\Facades\DB::table('pago')
    ->join('Credito', 'pago.CreditoID', '=', 'Credito.CreditoID')
    ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
    ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
    ->whereIn('pago.PagoID', [117269, 117261, 117349, 117352, 117343])
    ->select('pago.PagoID', 'pago.MontoPagado', 'Cliente.NombresApellidos')
    ->get();

echo "\n--- DETALLE PAGOS HUERFANOS ---\n";
foreach ($pagos as $pg) {
    echo "PagoID: {$pg->PagoID} | Monto: S/ {$pg->MontoPagado} | Cliente: {$pg->NombresApellidos}\n";
}
