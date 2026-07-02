<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== KEYS ACTUALES ===\n";
$keys = DB::select("SHOW INDEX FROM UserNivelAprobacion WHERE Key_name != 'PRIMARY'");
foreach ($keys as $k) {
    echo "  Key: {$k->Key_name} | Column: {$k->Column_name} | Unique: " . ($k->Non_unique ? 'NO' : 'SI') . "\n";
}

echo "\nIntentando DROP y recrear...\n";
try {
    // Buscar el nombre real del unique key
    $uniqueKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'UserNivelAprobacion' AND CONSTRAINT_TYPE = 'UNIQUE' AND CONSTRAINT_NAME != 'PRIMARY'");
    foreach ($uniqueKeys as $uk) {
        echo "  Encontrado: {$uk->CONSTRAINT_NAME}\n";
        try {
            DB::statement("ALTER TABLE UserNivelAprobacion DROP INDEX {$uk->CONSTRAINT_NAME}");
            echo "  DROP OK\n";
        } catch (\Exception $e) {
            echo "  DROP FAIL: " . $e->getMessage() . "\n";
        }
    }

    DB::statement("ALTER TABLE UserNivelAprobacion ADD UNIQUE KEY UQ_UserID_SedeID (UserID, SedeID)");
    echo "  ADD UNIQUE OK\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
