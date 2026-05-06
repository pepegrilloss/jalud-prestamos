<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\MovimientoFondo::truncate();
\App\Models\TransferenciaSede::truncate();
\App\Models\FondoSede::truncate();

// Update existing sedes if they were created just for testing, but it's fine to leave 'Sede Gerencia' as it is.
// Just to be safe, I'll delete the Gerencia I created since the user can create it themselves if needed,
// but actually they want to do the transfer from 0. I'll just leave the Sedes alone, and just clear the money.

echo "Base de datos limpia. Saldos reiniciados y transferencias eliminadas.";
