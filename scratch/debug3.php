<?php
require_once "C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php";
$app = require_once "C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST: Query with SedeID=2 ===\n";
$q = App\Models\AperturaCierreDia::withoutGlobalScope("sede")
    ->where("SedeID", 2)
    ->where("pagos_promotor_bloqueados", true)
    ->exists();
echo "Result: " . ($q ? "BLOQUEADO" : "NO BLOQUEADO") . "\n";

echo "\n=== TEST: Query with SedeID=3 ===\n";
$q = App\Models\AperturaCierreDia::withoutGlobalScope("sede")
    ->where("SedeID", 3)
    ->where("pagos_promotor_bloqueados", true)
    ->exists();
echo "Result: " . ($q ? "BLOQUEADO" : "NO BLOQUEADO") . "\n";

echo "\n=== TEST: RAW SQL ===\n";
$result = Illuminate\Support\Facades\DB::select("SELECT EXISTS(SELECT 1 FROM apertura_cierre_dia WHERE SedeID = 2 AND pagos_promotor_bloqueados = 1) as r");
echo "RAW for SedeID=2: " . ($result[0]->r ? "BLOQUEADO" : "NO BLOQUEADO") . "\n";
