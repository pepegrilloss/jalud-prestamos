<?php
/**
 * Crear pagos faltantes de exoneraciones aprobadas que no generaron pago
 * Ejecutar: php arreglar_pagos_exoneracion.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== ARREGLAR PAGOS DE EXONERACIONES ===\n\n";

// Buscar solicitudes aprobadas sin pago generado
$solicitudes = DB::select("
    SELECT se.SolicitudExoneracionID, se.CreditoID, se.MontoExonerado, se.FechaAprobacion,
           te.Codigo as TipoCodigo, te.Nombre as TipoNombre,
           c.SedeID, se.UserAprobadorID, se.ComentarioAprobacion
    FROM SolicitudExoneracion se
    JOIN TipoExoneracion te ON se.TipoExoneracionID = te.TipoExoneracionID
    JOIN Credito c ON se.CreditoID = c.CreditoID
    WHERE se.Estado = 'APROBADO'
      AND (se.PagoGeneradoID IS NULL OR se.PagoGeneradoID = 0)
      AND se.Activo = 1
");

if (empty($solicitudes)) {
    echo "No hay exoneraciones aprobadas sin pago.\n";
    exit;
}

echo "Encontradas: " . count($solicitudes) . "\n\n";

$creados = 0;

foreach ($solicitudes as $s) {
    // Obtener usuario aprobador
    $userName = 'Sistema';
    if ($s->UserAprobadorID) {
        $u = DB::table('users')->where('id', $s->UserAprobadorID)->first();
        $userName = $u ? ($u->name ?? 'Usuario #' . $s->UserAprobadorID) : 'Usuario #' . $s->UserAprobadorID;
    }

    // Obtener una cuota de referencia
    $cuotaRef = DB::table('cuota')
        ->where('CreditoID', $s->CreditoID)
        ->where('Activo', 1)
        ->orderBy('NumeroCuota')
        ->first();

    $fechaPago = $s->FechaAprobacion ?? now();

    $pagoID = DB::table('pago')->insertGetId([
        'CreditoID' => $s->CreditoID,
        'CuotaID' => $cuotaRef?->CuotaID,
        'MontoPagado' => $s->MontoExonerado,
        'FechaPago' => $fechaPago,
        'SedeID' => $s->SedeID,
        'TipoPago' => 'EFECTIVO',
        'EsPagoAutomatico' => 1,
        'TipoConcepto' => $s->TipoCodigo,
        'Comentario' => "Exoneración aprobada - {$s->TipoNombre} S/{$s->MontoExonerado}: {$s->ComentarioAprobacion}",
        'UsuarioRegistro' => $userName,
        'Activo' => 1,
    ]);

    // Vincular a la solicitud
    DB::table('SolicitudExoneracion')
        ->where('SolicitudExoneracionID', $s->SolicitudExoneracionID)
        ->update(['PagoGeneradoID' => $pagoID]);

    $creados++;
    echo "  [OK] SolicitudID={$s->SolicitudExoneracionID} | CreditoID={$s->CreditoID} | S/{$s->MontoExonerado} | PagoID={$pagoID}\n";
}

echo "\n=== RESUMEN ===\n";
echo "  Pagos creados: {$creados}\n";
echo "\nDone.\n";
