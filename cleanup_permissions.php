<?php

// Script para limpiar permisos personalizados innecesarios
// Ejecutar con: php artisan tinker < cleanup_permissions.php

use Spatie\Permission\Models\Permission;

// Permisos personalizados a CONSERVAR
$mantener = [
    'Abrir Dia Apertura',
    'Cerrar Dia Apertura',
    'Ver Todas Las Sedes',
];

// Permisos personalizados a ELIMINAR (los que se ven en la imagen)
$eliminar = [
    'Create Crear::proposicion::credito',
    'Delete Crear::proposicion::credito',
    'Update Crear::proposicion::credito',
    'View Any Crear::proposicion::credito',
    'View Crear::proposicion::credito',
    'Widget Apertura Cierre Dia Widget',
    'Widget Cliente Proposicion Stats',
    'Widget Cobranza Stats',
    'Widget Exoneraciones Pendientes Widget',
    'Widget Exoneraciones Stats Widget',
    'Widget Pagos Stats',
    'Widget Proposiciones Stats',
];

echo "=== Limpieza de Permisos Personalizados ===" . PHP_EOL;

$deleted = 0;
foreach ($eliminar as $permiso) {
    $p = Permission::where('name', $permiso)->first();
    if ($p) {
        // Desvincular de roles antes de eliminar
        $p->roles()->detach();
        $p->delete();
        echo "✅ Eliminado: {$permiso}" . PHP_EOL;
        $deleted++;
    } else {
        echo "⚠️  No encontrado: {$permiso}" . PHP_EOL;
    }
}

echo PHP_EOL . "Total eliminados: {$deleted}" . PHP_EOL;

// Verificar que los 3 que queremos mantener existan
echo PHP_EOL . "=== Permisos Personalizados Restantes ===" . PHP_EOL;
foreach ($mantener as $permiso) {
    $exists = Permission::where('name', $permiso)->exists();
    echo ($exists ? '✅' : '❌') . " {$permiso}" . PHP_EOL;
}

// Limpiar cache de permisos
app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
echo PHP_EOL . "✅ Cache de permisos limpiado." . PHP_EOL;
