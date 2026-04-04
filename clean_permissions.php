<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Los únicos que queremos conservar en la pestaña de "Personalizados"
$keep = [
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
    'ver_todas_las_sedes',
    'page_Themes'
];

// Buscamos todos los que Shield está mandando a "Personalizados"
// que son los 'delete_any_', los de páginas excluidas y widgets.
$toDelete = Permission::whereNotIn('name', $keep)
    ->where(function($query) {
        $query->where('name', 'like', 'delete_any_%')
              ->orWhere('name', 'like', 'page_%')
              ->orWhere('name', 'like', 'widget_%');
    })->get();

$count = $toDelete->count();

foreach ($toDelete as $permission) {
    echo "Eliminando: {$permission->name}\n";
    $permission->delete();
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "\n--- LIMPIEZA FINALIZADA ---\n";
echo "Se eliminaron {$count} permisos de la pestaña 'Personalizados'.\n";
echo "Quedaron activos: " . implode(', ', $keep) . "\n";
