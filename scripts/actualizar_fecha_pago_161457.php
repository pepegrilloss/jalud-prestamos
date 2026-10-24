<?php

use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['expected-db:', 'apply', 'confirm:']);
$expectedDatabase = $options['expected-db'] ?? null;
$apply = array_key_exists('apply', $options);
$confirmation = $options['confirm'] ?? null;

if (!$expectedDatabase) {
    fwrite(STDERR, "Falta --expected-db=NOMBRE_EXACTO_DE_LA_BD.\n");
    exit(2);
}

if ($apply && $confirmation !== 'CAMBIAR-FECHA-161457') {
    fwrite(STDERR, "Para aplicar, agregue --confirm=CAMBIAR-FECHA-161457.\n");
    exit(2);
}

if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "Abortado: este script requiere la conexión MySQL/MariaDB del proyecto.\n");
    exit(2);
}

$actualDatabase = DB::connection()->getDatabaseName();
if ($actualDatabase !== $expectedDatabase) {
    fwrite(STDERR, "Abortado: BD conectada '{$actualDatabase}' no coincide con '{$expectedDatabase}'.\n");
    exit(2);
}

$pagoId = 161457;
$montoEsperado = 1012.00;
$fechaActualEsperada = '2026-10-22 11:54:24';
$fechaNueva = '2025-08-22 11:54:24';

try {
    DB::beginTransaction();

    $pago = DB::table('pago')
        ->where('PagoID', $pagoId)
        ->lockForUpdate()
        ->first();

    if (!$pago || (int) $pago->CreditoID !== 484 || (int) $pago->SedeID !== 1 || (int) $pago->Activo !== 1 || round((float) $pago->MontoPagado, 2) !== $montoEsperado) {
        throw new RuntimeException('El pago ya cambió o no coincide con el pago corregido esperado.');
    }

    $fechaActual = substr((string) $pago->FechaPago, 0, 19);

    if ($fechaActual === $fechaNueva) {
        DB::rollBack();
        echo "La fecha del pago ya es {$fechaNueva}; no se modificó ningún dato.\n";
        exit(0);
    }

    if ($fechaActual !== $fechaActualEsperada) {
        throw new RuntimeException("FechaPago actual inesperada ({$fechaActual}); se requiere revisión manual.");
    }

    $resultado = [
        'modo' => $apply ? 'APLICAR' : 'SIMULACION (sin cambios)',
        'base_de_datos' => $actualDatabase,
        'pago_id' => $pagoId,
        'monto_sin_cambio' => $montoEsperado,
        'fecha_pago' => [$fechaActual, $fechaNueva],
        'movimientos_de_caja' => 'SIN CAMBIOS',
    ];

    if (!$apply) {
        DB::rollBack();
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo "Simulación solamente: no se modificó ningún dato.\n";
        exit(0);
    }

    $actualizados = DB::table('pago')
        ->where('PagoID', $pagoId)
        ->where('CreditoID', 484)
        ->where('MontoPagado', $montoEsperado)
        ->where('FechaPago', $fechaActualEsperada)
        ->where('Activo', 1)
        ->update(['FechaPago' => $fechaNueva]);

    if ($actualizados !== 1) {
        throw new RuntimeException('La actualización no afectó exactamente una fila; se hará rollback.');
    }

    DB::commit();
    $resultado['estado'] = 'FECHA ACTUALIZADA';
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    fwrite(STDERR, "Abortado sin cambios: {$exception->getMessage()}\n");
    exit(1);
}
