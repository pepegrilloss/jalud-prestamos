<?php
require_once 'C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$role = Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
if ($role) {
    $role->givePermissionTo('widget_MontoPropuestoHoyStatsWidget');
    echo 'OK - assigned to super_admin' . PHP_EOL;
} else {
    echo 'super_admin role not found' . PHP_EOL;
}
