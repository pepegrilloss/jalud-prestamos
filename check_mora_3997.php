<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$codigo = 'C-003997';

$p = DB::table('ProposicionCredito')->where('CodigoCredito', $codigo)->first(['ProposicionCreditoID', 'SaldoPendiente']);
if (!$p) { echo "No encontrado\n"; exit; }

$c = DB::table('Credito')->where('ProposicionCreditoID', $p->ProposicionCreditoID)->first(['CreditoID']);
if (!$c) { echo "Sin credito asociado\n"; exit; }

$m = DB::table('mora')->where('CreditoID', $c->CreditoID)->orderByDesc('FechaMora')->first(['MoraAcumulada', 'MontoMora', 'FechaMora']);
$exo = DB::table('pago')->where('CreditoID', $c->CreditoID)->where('TipoConcepto', 'M')->sum('MontoPagado');

echo "C-003997:\n";
echo "  SaldoPendiente: S/ " . number_format($p->SaldoPendiente, 2) . "\n";

if ($m) {
    echo "  Ultima mora: {$m->FechaMora}\n";
    echo "  MoraAcumulada: S/ " . number_format($m->MoraAcumulada, 2) . "\n";
    echo "  Mora ya exonerada: S/ " . number_format($exo, 2) . "\n";
    $pendiente = (float)$m->MoraAcumulada - (float)$exo;
    echo "  Mora pendiente de exonerar: S/ " . number_format($pendiente, 2) . "\n";
} else {
    echo "  Mora: NO TIENE\n";
}
