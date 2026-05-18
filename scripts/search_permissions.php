<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$names = [
    'ver_todas_las_sedes',
    'abrir_dia_apertura',
    'cerrar_dia_apertura',
    'page_Themes',
];

foreach ($names as $name) {
    $exists = Permission::where('name', $name)->exists();
    echo "$name: " . ($exists ? "EXISTE" : "NO EXISTE") . "\n";
}

// Buscar cualquier cosa que se parezca a lo del screenshot
$patterns = ['%crear%', '%Crear%', '%proposicion%', '%widget%', '%Widget%'];
foreach ($patterns as $pattern) {
    $count = Permission::where('name', 'like', $pattern)->count();
    echo "Pattern $pattern: $count matches\n";
    if ($count > 0 && $count < 20) {
        print_r(Permission::where('name', 'like', $pattern)->pluck('name')->toArray());
    }
}
