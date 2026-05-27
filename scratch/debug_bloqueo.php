<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$latest = App\Models\AperturaCierreDia::withoutGlobalScope("sede")->latest()->first();
echo "Latest ID: " . ($latest->AperturaCierreDiaID ?? "null") . PHP_EOL;
echo "EstadoDia: " . ($latest->EstadoDia ?? "null") . PHP_EOL;
echo "pagos_promotor_bloqueados: " . var_export($latest->pagos_promotor_bloqueados ?? false, true) . PHP_EOL;
echo "SedeID: " . ($latest->SedeID ?? "null") . PHP_EOL;
echo PHP_EOL;

$all = App\Models\AperturaCierreDia::withoutGlobalScope("sede")->orderBy("AperturaCierreDiaID", "desc")->get();
echo "Total records: " . $all->count() . PHP_EOL;
foreach ($all as $r) {
    echo "  ID=" . $r->AperturaCierreDiaID . " Fecha=" . ($r->Fecha ?? "null") . " Estado=" . $r->EstadoDia . " Bloqueado=" . var_export($r->pagos_promotor_bloqueados ?? false, true) . " SedeID=" . ($r->SedeID ?? "null") . PHP_EOL;
}
