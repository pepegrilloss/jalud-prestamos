<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// 1. Asegurar que 'page_Mantenimiento' y los básicos existan
$essential = [
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
    'ver_todas_las_sedes',
    'page_Themes',
    'page_Mantenimiento',
    'page_SelectSede',
    'page_EvaluacionDeCredito'
];

foreach ($essential as $name) {
    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

// 2. Identificar ÚNICAMENTE la basura (los que tienen espacios y mayúsculas)
// Estos son los que Shield leyó de la DB pero no son suyos oficiales.
$toDelete = Permission::where(function($query) {
        $query->where('name', 'like', 'Delete Any %')
              ->orWhere('name', 'like', 'Page %')
              ->orWhere('name', 'like', 'Widget %');
    })->get();

$count = $toDelete->count();

foreach ($toDelete as $permission) {
    echo "Eliminando basura real: {$permission->name}\n";
    $permission->delete();
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "\n--- LIMPIEZA SEGURA COMPLETADA ---\n";
echo "Se eliminaron {$count} permisos basura.\n";
echo "Se restauraron/mantuvieron los permisos críticos de Páginas y Clusters.\n";
