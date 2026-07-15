<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CumplimientoSbsPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'acceder_cumplimiento_sbs',
            'ver_todos_los_casos_sbs',
            'gestionar_catalogos_sbs',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $rol = Role::findOrCreate('oficial_cumplimiento_sbs', 'web');
        $rol->syncPermissions($permisos);
    }
}
