<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$keepNames = [
    'ver_todas_las_sedes',
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
];

// Prefijos estandar que queremos mantener
$standardPrefixes = [
    'view_', 'view_any_', 'create_', 'update_', 'delete_', 'restore_', 'force_delete_',
    'page_', 'widget_'
];

$permissions = Permission::all();
$deleted = [];

foreach ($permissions as $permission) {
    $name = $permission->name;
    
    // Si esta en la lista de los que queremos mantener expresamente, lo saltamos
    if (in_array($name, $keepNames)) {
        continue;
    }
    
    // Si empieza con un prefijo estandar de Shield, lo saltamos (son recursos, paginas o widgets legitimos)
    $isStandard = false;
    foreach ($standardPrefixes as $prefix) {
        if (str_starts_with($name, $prefix)) {
            $isStandard = true;
            break;
        }
    }
    
    if ($isStandard) {
        continue;
    }
    
    // Si llegamos aqui, es un permiso "basura" o personalizado que ya no queremos
    $deleted[] = $name;
    $permission->delete();
}

echo "Permisos eliminados:\n";
print_r($deleted);
echo "\nLimpieza completada.\n";
