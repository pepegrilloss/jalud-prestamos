<?php

use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['expected-db:', 'user-id:', 'apply', 'confirm:']);
$expectedDatabase = $options['expected-db'] ?? null;
$userId = isset($options['user-id']) ? (int) $options['user-id'] : null;
$apply = array_key_exists('apply', $options);
$confirmation = $options['confirm'] ?? null;

if (!$expectedDatabase) {
    fwrite(STDERR, "Falta --expected-db=NOMBRE_EXACTO_DE_LA_BD.\n");
    exit(2);
}

if (!$userId || $userId < 1) {
    fwrite(STDERR, "Falta --user-id=ID_DEL_USUARIO_QUE_EJECUTA_LA_CORRECCION.\n");
    exit(2);
}

if ($apply && $confirmation !== 'APLICAR-161457') {
    fwrite(STDERR, "Para aplicar, agregue --confirm=APLICAR-161457.\n");
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
$creditoId = 484;
$sedeId = 1;
$montoActual = 1021.00;
$montoCorregido = 1012.00;
$fechaPagoEsperada = '2026-10-22 11:54:24';
$diferencia = 9.00;
$proposicionId = 484;
$saldoEsperado = 81.00;
$saldoCorregido = 90.00;
$montoTotalEsperado = 1160.00;
$observacion = "Corrección de monto del pago #{$pagoId}: diferencia S/ 9.00";

try {
    DB::beginTransaction();

    // Use the same lock order as the payment flow where possible.
    $credito = DB::table('Credito')
        ->where('CreditoID', $creditoId)
        ->lockForUpdate()
        ->first();

    $proposicion = DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $proposicionId)
        ->lockForUpdate()
        ->first();

    $pago = DB::table('pago')
        ->where('PagoID', $pagoId)
        ->lockForUpdate()
        ->first();

    $fondo = DB::table('fondo_sedes')
        ->where('SedeID', $sedeId)
        ->lockForUpdate()
        ->first();

    if (!$credito || (int) $credito->ProposicionCreditoID !== $proposicionId || (int) $credito->SedeID !== $sedeId || (int) $credito->Activo !== 1 || $credito->EstatusCreditoFinal !== 'ACTIVO') {
        throw new RuntimeException('El crédito no coincide con los datos esperados o ya no está activo.');
    }

if (!$proposicion || (int) $proposicion->Activo !== 1 || $proposicion->CodigoCredito !== 'C-000484' || round((float) $proposicion->MontoTotalPagar, 2) !== $montoTotalEsperado || round((float) $proposicion->SaldoPendiente, 2) !== $saldoEsperado) {
        throw new RuntimeException('La proposición o su saldo cambiaron. No se aplicó la corrección.');
    }

if (!$pago || (int) $pago->CreditoID !== $creditoId || (int) $pago->SedeID !== $sedeId || (int) $pago->Activo !== 1 || (int) $pago->EsMora !== 0 || (int) $pago->EsPagoForzado !== 1 || $pago->TipoConcepto !== 'C' || substr((string) $pago->FechaPago, 0, 19) !== $fechaPagoEsperada || round((float) $pago->MontoPagado, 2) !== $montoActual) {
        throw new RuntimeException('El pago ya cambió o no coincide con los datos esperados. No se aplicó la corrección.');
    }

    if (!$fondo) {
        throw new RuntimeException('No existe el fondo de la sede 1.');
    }

    $pagosPosteriores = DB::table('pago')
        ->where('CreditoID', $creditoId)
        ->where('Activo', 1)
        ->where('PagoID', '>', $pagoId)
        ->count();

    if ($pagosPosteriores > 0) {
        throw new RuntimeException("Hay {$pagosPosteriores} pago(s) posterior(es) activo(s) en el crédito. Debe revisarse el saldo antes de corregir.");
    }

    $movimientoOriginal = DB::table('movimientos_fondo')
        ->where('SedeID', $sedeId)
        ->where('Tipo', 'INGRESO_RECAUDO')
        ->where('Monto', $montoActual)
        ->where('Observacion', "Recaudo de pago #{$pagoId}")
        ->lockForUpdate()
        ->get();

    if ($movimientoOriginal->count() !== 1) {
        throw new RuntimeException('No se encontró exactamente un movimiento original de recaudo por S/ 1,021.00.');
    }

$reversionesPrevias = DB::table('movimientos_fondo')
        ->where('SedeID', $sedeId)
        ->where('Tipo', 'REVERSION_RECAUDO')
    ->where('Observacion', 'like', "%#{$pagoId}%")
    ->count();

if ($reversionesPrevias > 0) {
    throw new RuntimeException("Ya existe(n) {$reversionesPrevias} reversión(es) relacionada(s) al pago. No se aplicó una segunda vez; revise esos movimientos primero.");
    }

    $usuarioExiste = DB::table('users')->where('id', $userId)->exists();
    if (!$usuarioExiste) {
        throw new RuntimeException("No existe el usuario {$userId} en esta base de datos.");
    }

    $diasAbiertos = DB::table('apertura_cierre_dia')
        ->where('SedeID', $sedeId)
        ->where('EstadoDia', 'ABIERTO')
        ->orderByDesc('Fecha')
        ->lockForUpdate()
        ->get();

    if ($diasAbiertos->count() > 1) {
        throw new RuntimeException('Hay más de un día abierto para la sede. No se eligió una fecha contable automáticamente.');
    }

    $fechaBase = $diasAbiertos->first()?->Fecha
        ? Illuminate\Support\Carbon::parse($diasAbiertos->first()->Fecha)
        : now();
    $fechaMovimiento = $fechaBase->copy()->setTime(now()->hour, now()->minute, now()->second);

    $saldoCajaAnterior = round((float) $fondo->Saldo, 2);
    $saldoCajaNuevo = round($saldoCajaAnterior - $diferencia, 2);

    $resultado = [
        'modo' => $apply ? 'APLICAR' : 'SIMULACION (sin cambios)',
        'base_de_datos' => $actualDatabase,
        'pago_id' => $pagoId,
        'monto_pago' => [$montoActual, $montoCorregido],
        'fecha_pago' => $fechaPagoEsperada,
        'saldo_credito' => [$saldoEsperado, $saldoCorregido],
        'caja_sede_1' => [$saldoCajaAnterior, $saldoCajaNuevo],
        'fecha_contable_movimiento' => $fechaMovimiento->toDateTimeString(),
        'usuario_id' => $userId,
    ];

    if (!$apply) {
        DB::rollBack();
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo "Simulación solamente: no se modificó ningún dato.\n";
        exit(0);
    }

    fwrite(STDOUT, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

    $pagoActualizado = DB::table('pago')
        ->where('PagoID', $pagoId)
        ->where('MontoPagado', $montoActual)
        ->where('FechaPago', $fechaPagoEsperada)
        ->where('Activo', 1)
        ->update([
            'MontoPagado' => $montoCorregido,
            'FechaPago' => $fechaPagoEsperada,
            'UserModificacionID' => $userId,
            'FechaModificacion' => $fechaMovimiento,
        ]);

    $saldoActualizado = DB::table('ProposicionCredito')
        ->where('ProposicionCreditoID', $proposicionId)
        ->where('SaldoPendiente', $saldoEsperado)
        ->update([
            'SaldoPendiente' => $saldoCorregido,
            'UserModificacionID' => $userId,
            'FechaModificacion' => $fechaMovimiento,
        ]);

    $cajaActualizada = DB::table('fondo_sedes')
        ->where('SedeID', $sedeId)
        ->where('Saldo', $saldoCajaAnterior)
        ->update(['Saldo' => $saldoCajaNuevo]);

    if ($pagoActualizado !== 1 || $saldoActualizado !== 1 || $cajaActualizada !== 1) {
        throw new RuntimeException('Una validación de actualización no afectó exactamente una fila. Se hará rollback.');
    }

    $movimientoId = DB::table('movimientos_fondo')->insertGetId([
        'SedeID' => $sedeId,
        'Tipo' => 'REVERSION_RECAUDO',
        'Monto' => -$diferencia,
        'SaldoAnterior' => $saldoCajaAnterior,
        'SaldoNuevo' => $saldoCajaNuevo,
        'TransferenciaID' => null,
        'UsuarioID' => $userId,
        'Observacion' => $observacion,
        'FechaMovimiento' => $fechaMovimiento,
        'created_at' => $fechaMovimiento,
        'updated_at' => $fechaMovimiento,
    ], 'MovimientoID');

    if (!$movimientoId) {
        throw new RuntimeException('No se pudo confirmar la creación del movimiento de ajuste. Se hará rollback.');
    }

    DB::commit();
    $resultado['movimiento_id_creado'] = $movimientoId;
    $resultado['estado'] = 'CORRECCION APLICADA';
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    fwrite(STDERR, "Abortado sin cambios: {$exception->getMessage()}\n");
    exit(1);
}
