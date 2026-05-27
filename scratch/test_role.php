<?php
require_once 'C:/xampp/htdocs/jalud-prestamos/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/jalud-prestamos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(12);
if ($user) {
    echo 'User: ' . $user->name . PHP_EOL;
    echo 'SedeID: ' . ($user->SedeID ?? 'NULL') . PHP_EOL;
    echo 'HasRole(Promotor Cobrador): ' . ($user->hasRole('Promotor Cobrador') ? 'YES' : 'NO') . PHP_EOL;
    echo 'HasRole(promotor_cobrador): ' . ($user->hasRole('promotor_cobrador') ? 'YES' : 'NO') . PHP_EOL;
    $roles = $user->getRoleNames();
    echo 'All roles: ';
    foreach ($roles as $r) { echo '[' . $r . '] '; }
    echo PHP_EOL;
    
    echo PHP_EOL . '=== DIRECT DB CHECK ===' . PHP_EOL;
    $exists = Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
        ->where('pagos_promotor_bloqueados', 1)
        ->where('SedeID', $user->SedeID)
        ->exists();
    echo 'Bloqueado for SedeID=' . $user->SedeID . ': ' . ($exists ? 'YES (blocked)' : 'NO (not blocked)') . PHP_EOL;
    
    $exists2 = Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
        ->where('pagos_promotor_bloqueados', 1)
        ->exists();
    echo 'Bloqueado ANY sede: ' . ($exists2 ? 'YES' : 'NO') . PHP_EOL;
    
    // Test the exact query that ListPagos runs
    echo PHP_EOL . '=== SIMULATING ListPagos EXACT QUERY ===' . PHP_EOL;
    $bloqueado = $user->hasRole('Promotor Cobrador')
        && Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
            ->where('pagos_promotor_bloqueados', 1)
            ->where('SedeID', $user->SedeID)
            ->exists();
    echo 'With Promotor Cobrador: ' . ($bloqueado ? 'BLOQUEADO' : 'NO BLOQUEADO') . PHP_EOL;
    
    $bloqueado2 = $user->hasRole('promotor_cobrador')
        && Illuminate\Support\Facades\DB::table('apertura_cierre_dia')
            ->where('pagos_promotor_bloqueados', 1)
            ->where('SedeID', $user->SedeID)
            ->exists();
    echo 'With promotor_cobrador: ' . ($bloqueado2 ? 'BLOQUEADO' : 'NO BLOQUEADO') . PHP_EOL;
}
