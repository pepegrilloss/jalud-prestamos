<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$permisos = ['aprobar_extornos', 'aprobar_exoneraciones'];

foreach ($permisos as $name) {
    if (DB::table('permissions')->where('name', $name)->exists()) {
        echo "Ya existe: {$name}\n";
        continue;
    }
    $id = DB::table('permissions')->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Creado: {$name} (ID={$id})\n";

    $role = DB::table('roles')->where('name', 'super_admin')->first();
    if ($role) {
        DB::table('role_has_permissions')->insert([
            'permission_id' => $id,
            'role_id' => $role->id,
        ]);
        echo "  Asignado a super_admin\n";
    }
}
echo "\nDone.\n";
