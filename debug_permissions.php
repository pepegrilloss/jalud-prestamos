<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

$permissions = Permission::where('name', 'like', '%sede%')->pluck('name')->toArray();
echo "Permissions found: " . implode(', ', $permissions) . "\n";
