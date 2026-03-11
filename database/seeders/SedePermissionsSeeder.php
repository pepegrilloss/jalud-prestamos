<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use BezhanSalleh\FilamentShield\Support\Utils;

class SedePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear el permiso 'ver_todas_las_sedes' si no existe
        $permission = Permission::firstOrCreate([
            'name' => 'ver_todas_las_sedes',
            'guard_name' => 'web' // o el guard que uses para Filament
        ]);

        // 2. Buscar el rol super_admin definido en Filament Shield
        $superAdminRole = Role::where('name', Utils::getSuperAdminName())->first();

        // 3. Asignar el permiso automáticamente al super_admin si existe
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permission);
        }
    }
}
