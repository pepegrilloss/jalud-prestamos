<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$name = 'editar_capital_tasa';
$ex = DB::table('permissions')->where('name', $name)->exists();

if ($ex) {
    echo "Ya existe\n";
    exit;
}

$id = DB::table('permissions')->insertGetId([
    'name' => $name,
    'guard_name' => 'web',
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Permiso creado: {$id}\n";

$r = DB::table('roles')->where('name', 'super_admin')->first();
if ($r) {
    DB::table('role_has_permissions')->insert([
        'permission_id' => $id,
        'role_id' => $r->id,
    ]);
    echo "Asignado a super_admin\n";
} else {
    echo "Rol super_admin no encontrado\n";
}
