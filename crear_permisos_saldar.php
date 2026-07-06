<?php
/**
 * Crear permisos Shield para SaldarCreditoResource
 * Ejecutar: php crear_permisos_saldar.php
 */
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$role = DB::table('roles')->where('name', 'super_admin')->first();
if (!$role) { echo "super_admin no encontrado\n"; exit; }

$permisos = [
    'view_any_saldar::credito',
    'view_saldar::credito',
    'update_saldar::credito',
];

foreach ($permisos as $name) {
    $ex = DB::table('permissions')->where('name', $name)->first();
    if ($ex) { echo "Ya existe: {$name} (ID={$ex->id})\n"; continue; }

    $id = DB::table('permissions')->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('role_has_permissions')->insert([
        'permission_id' => $id,
        'role_id' => $role->id,
    ]);

    echo "Creado: {$name} (ID={$id})\n";
}

echo "\nDone.\n";
