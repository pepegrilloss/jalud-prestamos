<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// 1. Restaurar permisos manuales críticos para que el menú aparezca
$critical = [
    'ver_todas_las_sedes',
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
    'page_Themes',
    'page_Mantenimiento',
    'page_SelectSede',
    'page_EvaluacionDeCredito'
];

foreach ($critical as $name) {
    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

// 2. Borrar ÚNICAMENTE la basura con espacios (los que dicen explicitamente "Delete Any ...")
// Y que NO estén en nuestra lista crítica.
$toDelete = Permission::where(function($query) {
        $query->where('name', 'like', 'Delete Any %')
              ->orWhere('name', 'like', 'Page %')
              ->orWhere('name', 'like', 'Widget %');
    })
    ->whereNotIn('name', $critical)
    ->get();

foreach ($toDelete as $permission) {
    echo "Eliminando basura: {$permission->name}\n";
    $permission->delete();
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "\n--- TODO RESTAURADO Y LIMPIO ---\n";
echo "Tu Cluster de Mantenimiento ya debe ser visible nuevamente.\n";
echo "Las tablas (Ciudad, etc) aparecerán en 'Recursos' tras el generate.\n";
