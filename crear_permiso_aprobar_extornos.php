<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$name = 'aprobar_extornos';

if (DB::table('permissions')->where('name', $name)->exists()) {
    echo "El permiso '{$name}' ya existe\n";
    exit;
}

$id = DB::table('permissions')->insertGetId([
    'name' => $name,
    'guard_name' => 'web',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Permiso '{$name}' creado (ID={$id})\n";

$role = DB::table('roles')->where('name', 'super_admin')->first();
if ($role) {
    DB::table('role_has_permissions')->insert([
        'permission_id' => $id,
        'role_id' => $role->id,
    ]);
    echo "Asignado a super_admin\n";
} else {
    echo "Rol super_admin no encontrado\n";
}
