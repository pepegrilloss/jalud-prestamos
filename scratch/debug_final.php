<?php
require_once "C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php";
$app = require_once "C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== REGISTROS CON BLOQUEO ===\n";
$records = App\Models\AperturaCierreDia::withoutGlobalScope("sede")->where("pagos_promotor_bloqueados", true)->get();
if ($records->isEmpty()) {
    echo "NO HAY NINGUNO\n";
} else {
    foreach ($records as $r) {
        echo "ID=" . $r->AperturaCierreDiaID . " SedeID=" . ($r->SedeID ?? "null") . " Fecha=" . ($r->Fecha ?? "null") . "\n";
    }
}

echo "\n=== ULTIMO REGISTRO POR SEDE ===\n";
$sedes = App\Models\Sede::where("Activo", true)->get();
foreach ($sedes as $s) {
    $latest = App\Models\AperturaCierreDia::withoutGlobalScope("sede")
        ->where("SedeID", $s->SedeID)
        ->orderBy("AperturaCierreDiaID", "desc")
        ->first();
    if ($latest) {
        echo "Sede: " . $s->Nombre . " (ID=" . $s->SedeID . ") -> UltimoID=" . $latest->AperturaCierreDiaID . " Bloqueado=" . var_export((bool)$latest->pagos_promotor_bloqueados, true) . "\n";
    } else {
        echo "Sede: " . $s->Nombre . " (ID=" . $s->SedeID . ") -> Sin registros\n";
    }
}
