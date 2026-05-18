<?php
require '../vendor/autoload.php';
$app = require_once '../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pago4 = App\Models\Pago::find(4)->toArray();
$pago14 = App\Models\Pago::find(14)->toArray();
dump(['Pago 4 (Trasladado)' => $pago4, 'Pago 14 (Normal)' => $pago14]);
