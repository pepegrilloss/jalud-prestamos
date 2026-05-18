<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$prefixes = ['view_', 'create_', 'update_', 'delete_', 'restore_', 'force_delete_', 'page_', 'widget_'];

$permissions = Permission::all();
echo "PERMISOS NO ESTANDAR EN DB:\n";
foreach ($permissions as $p) {
    $isStandard = false;
    foreach ($prefixes as $prefix) {
        if (str_starts_with($p->name, $prefix)) {
            $isStandard = true;
            break;
        }
    }
    
    if (!$isStandard) {
        echo $p->name . "\n";
    }
}
