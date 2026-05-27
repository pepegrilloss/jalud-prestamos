<?php
require_once "C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php";
\ = require_once "C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php";
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== USERS ===\n";
\ = Illuminate\Support\Facades\DB::select("
    SELECT u.id, u.name, u.SedeID, u.PromotorCobradorID, 
           GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as roles
    FROM users u 
    LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id 
    LEFT JOIN roles r ON mhr.role_id = r.id 
    GROUP BY u.id, u.name, u.SedeID, u.PromotorCobradorID 
    ORDER BY u.id
");
foreach (\ as \) {
    echo "ID=" . \->id 
        . " Name=" . \->name 
        . " SedeID=" . (\->SedeID ?? "NULL") 
        . " PromotorID=" . (\->PromotorCobradorID ?? "NULL") 
        . " Roles=" . (\->roles ?? "NONE") 
        . PHP_EOL;
}

echo PHP_EOL . "=== CHECK with SedeID=2 ===\n";
\ = Illuminate\Support\Facades\DB::table("apertura_cierre_dia")
    ->where("pagos_promotor_bloqueados", 1)
    ->where("SedeID", 2)
    ->exists();
echo "Result: " . (\ ? "BLOQUEADO" : "NO BLOQUEADO") . PHP_EOL;
