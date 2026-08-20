<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('username', 'like', '%lcastaneda%')
    ->orWhere('name', 'like', '%casta%')
    ->first();

if (!$user) {
    echo "Usuario no encontrado por username o name\n";
    $users = \App\Models\User::all(['id', 'name', 'username', 'PromotorCobradorID', 'SedeID']);
    foreach ($users as $u) {
        echo "ID: {$u->id} | User: {$u->username} | Name: {$u->name} | Roles: " . implode(', ', $u->getRoleNames()->toArray()) . " | PromotorID: {$u->PromotorCobradorID}\n";
    }
} else {
    echo "Usuario encontrado: {$user->username} ({$user->name})\n";
    echo "Roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";
    echo "PromotorCobradorID: {$user->PromotorCobradorID}\n";
    if ($user->promotorCobrador) {
        echo "Promotor: {$user->promotorCobrador->Descripcion} | ZonaID: {$user->promotorCobrador->ZonaID}\n";
        if ($user->promotorCobrador->zona) {
            echo "Zona: {$user->promotorCobrador->zona->Nombre}\n";
        }
    } else {
        echo "NO tiene promotorCobrador asociado en User model\n";
    }
}
