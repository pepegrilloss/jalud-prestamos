<?php
/**
 * Aplicar cambios para soft-delete de creditos
 * Ejecutar: php aplicar_eliminar_credito.php
 */
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== APLICAR SOFT-DELETE DE CREDITOS ===\n\n";

// 1. Agregar columnas
echo "--- Agregando columnas a ProposicionCredito ---\n";
try {
    DB::statement("ALTER TABLE ProposicionCredito
        ADD COLUMN Eliminado TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN FechaEliminacion DATETIME NULL,
        ADD COLUMN UserEliminacionID BIGINT NULL,
        ADD COLUMN MotivoEliminacion VARCHAR(255) NULL");
    echo "  Columnas agregadas OK\n";
} catch (\Exception $e) {
    echo "  Columnas ya existian o error: " . $e->getMessage() . "\n";
}

// 2. Crear permiso en BD
echo "\n--- Creando permiso eliminar_credito ---\n";
$name = 'eliminar_credito';
$ex = DB::table('permissions')->where('name', $name)->first();
if (!$ex) {
    $id = DB::table('permissions')->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $role = DB::table('roles')->where('name', 'super_admin')->first();
    DB::table('role_has_permissions')->insert([
        'permission_id' => $id,
        'role_id' => $role->id,
    ]);
    echo "  Permiso '$name' creado (ID=$id) y asignado a super_admin\n";
} else {
    echo "  Permiso ya existe (ID={$ex->id})\n";
}

echo "\nDone.\n";
