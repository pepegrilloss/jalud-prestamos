<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$permissions = Permission::pluck('name')->toArray();
echo "TODOS LOS PERMISOS EN DB:\n";
print_r($permissions);
