<?php
/**
 * Corregir cadena de refinanciamiento C-006060 -> C-005836
 * Ejecutar: php corregir_refi_6060.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== CORREGIR REFINANCIAMIENTO C-006060 → C-005836 ===\n";

DB::beginTransaction();

// 1. Vincular C-006060 a C-005836
DB::table('ProposicionCredito')
    ->where('CodigoCredito', 'C-006060')
    ->update(['ProposicionCreditoAnteriorID' => 5836]);
echo "[OK] C-006060 → AntID=5836 (C-005836)\n";

// 2. Corregir montos de C-006060
DB::table('ProposicionCredito')
    ->where('CodigoCredito', 'C-006060')
    ->update([
        'MontoTotalPagar' => 1564.92,
        'SaldoPendiente' => 1564.92,
    ]);
echo "[OK] C-006060: MontoTotalPagar=1564.92, SaldoPendiente=1564.92\n";

// 3. Eliminar pago automatico erroneo de C-000409 (CreditoID=409)
$pagoErr = DB::table('pago')->where('PagoID', 111229)->where('EsPagoAutomatico', 1)->first();
if ($pagoErr) {
    DB::table('pago')->where('PagoID', 111229)->delete();
    echo "[OK] PagoID=111229 (auto) eliminado de C-000409\n";
}

// 4. Crear pago automatico correcto en C-005836 (CreditoID=5836)
$cuotaRef = DB::table('cuota')->where('CreditoID', 5836)->where('Activo', 1)->orderBy('NumeroCuota')->first();
$pagoID = DB::table('pago')->insertGetId([
    'CreditoID' => 5836,
    'CuotaID' => $cuotaRef?->CuotaID,
    'MontoPagado' => 1449,
    'FechaPago' => now(),
    'SedeID' => 1,
    'TipoPago' => 'EFECTIVO',
    'EsPagoAutomatico' => 1,
    'TipoConcepto' => 'C',
    'Comentario' => 'Pago automatico por refinanciamiento C-006060. Saldo: S/1449',
    'UsuarioRegistro' => 'Sistema',
    'Activo' => 1,
]);
echo "[OK] PagoID={$pagoID} creado en C-005836 por S/1449\n";

// 5. Marcar C-005836 como refinanciado y saldado
DB::table('ProposicionCredito')->where('CodigoCredito', 'C-005836')->update([
    'SaldoPendiente' => 0,
    'FueRefinanciada' => 1,
    'Activo' => 0,
]);
DB::table('Credito')->where('ProposicionCreditoID', 5836)->update([
    'EstatusCreditoFinal' => 'SALDADO',
    'FechaSaldamiento' => now(),
]);
echo "[OK] C-005836 → Saldo=0, FueRefi=1, Activo=0, SALDADO\n";

// 6. Cerrar C-000409 (saldo a 0)
DB::table('ProposicionCredito')->where('CodigoCredito', 'C-000409')->update([
    'SaldoPendiente' => 0,
]);
echo "[OK] C-000409 → SaldoPendiente=0\n";

DB::commit();

echo "\n=== TODO CORREGIDO ===\n";
echo "C-006060: AntID=5836, MontoTotalPagar=1564.92 (1449+115.92)\n";
echo "C-005836: Saldado con auto-pago de S/1449\n";
echo "C-000409: Saldo a 0\n";
echo "\nDone.\n";
