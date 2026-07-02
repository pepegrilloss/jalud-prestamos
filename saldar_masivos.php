<?php
/**
 * Saldar creditos masivamente - Chiclayo
 *
 * Ejecutar: php saldar_masivos.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

set_time_limit(0);
ini_set('memory_limit', '256M');

$codigos = [
    'C-000509','C-000944','C-000987','C-001621','C-002495','C-002501','C-002424',
    'C-000746','C-002480','C-002345','C-001023','C-002253','C-002041','C-000750',
    'C-002268','C-002332','C-002231','C-000985','C-000524','C-000568','C-001539',
    'C-001065','C-001858','C-000574','C-001839','C-002488','C-000902','C-000975',
    'C-001060','C-002108','C-001055','C-001773','C-002135','C-002284','C-001762',
    'C-000609','C-001045','C-000693','C-002292','C-001272','C-001767','C-001547',
    'C-002063','C-002065','C-001093','C-001318','C-002309','C-000997','C-002186',
    'C-001670','C-002361','C-002188','C-002037','C-001940','C-001841','C-000692',
    'C-002072','C-001054','C-001077','C-001813','C-001789','C-000807','C-001053',
    'C-001819','C-000599','C-000903','C-002360','C-002408','C-002100','C-001780',
    'C-000456','C-000752','C-001577','C-002426','C-002481','C-002049','C-001488',
    'C-001149','C-001987','C-002071','C-001790','C-001824','C-002129','C-001259',
    'C-002009','C-000556','C-001373','C-002073','C-000868','C-001737','C-001639',
    'C-000709','C-001095','C-001209','C-000800','C-000634','C-001753','C-001425',
    'C-002393','C-002350','C-001876','C-000809','C-002244','C-000536','C-002047',
    'C-001135','C-000417','C-000822','C-001688','C-000803','C-000572','C-001069',
    'C-001750','C-002132','C-001947','C-002048','C-001246','C-000498','C-000760',
    'C-000442','C-000689','C-000419','C-001879','C-000880','C-000462','C-001368',
    'C-001150','C-001902','C-001908','C-002464','C-001955','C-001276','C-001211',
];

$codigos = array_unique($codigos);

echo "=== SALDAR " . count($codigos) . " CREDITOS (CHICLAYO) ===\n\n";

DB::beginTransaction();

$saldados = 0;
$noEncontrados = 0;
$yaSaldados = 0;
$cuotasAct = 0;

foreach ($codigos as $codigo) {
    $prop = DB::table('ProposicionCredito')
        ->where('CodigoCredito', $codigo)
        ->where('SedeID', 1)
        ->first();

    if (!$prop) {
        echo "  [??] {$codigo} -> No encontrado\n";
        $noEncontrados++;
        continue;
    }

    if ((float)$prop->SaldoPendiente <= 0) {
        $yaSaldados++;
        continue;
    }

    DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->update(['SaldoPendiente' => 0]);

    $credito = DB::table('Credito')
        ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
        ->first();

    if ($credito) {
        DB::table('Credito')
            ->where('CreditoID', $credito->CreditoID)
            ->update([
                'EstatusCreditoFinal' => 'SALDADO',
                'FechaSaldamiento' => now(),
            ]);

        $n = DB::table('cuota')
            ->where('CreditoID', $credito->CreditoID)
            ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
            ->update([
                'Estado' => 'PAGADA',
                'FechaPago' => now(),
            ]);
        $cuotasAct += $n;
    }

    $saldados++;
    echo "  [OK] {$codigo}\n";
}

DB::commit();

echo "\n=== RESUMEN ===\n";
echo "  Saldados:        {$saldados}\n";
echo "  Ya estaban:      {$yaSaldados}\n";
echo "  No encontrados:  {$noEncontrados}\n";
echo "  Cuotas actualizadas: {$cuotasAct}\n";
echo "\nDone.\n";
