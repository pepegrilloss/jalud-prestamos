<?php
/**
 * Asignar TODOS los permisos al rol super_admin
 * Ejecutar: php asignar_todos_permisos_admin.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$role = DB::table('roles')->where('name', 'super_admin')->first();
if (!$role) { echo "Rol super_admin no encontrado\n"; exit; }

$permisos = DB::table('permissions')->pluck('id');
$asignados = DB::table('role_has_permissions')->where('role_id', $role->id)->pluck('permission_id')->toArray();

$nuevos = 0;
foreach ($permisos as $pid) {
    if (!in_array($pid, $asignados)) {
        DB::table('role_has_permissions')->insert([
            'permission_id' => $pid,
            'role_id' => $role->id,
        ]);
        $nuevos++;
    }
}

echo "Total permisos: " . count($permisos) . "\n";
echo "Ya asignados: " . count($asignados) . "\n";
echo "Recien asignados: {$nuevos}\n";
echo "\nDone.\n";
