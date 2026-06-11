<?php
/**
 * MIGRACIÓN COMPLETA CCARTERA → jalud-prestamos
 * 
 * Ejecutar UNA SOLA VEZ en el servidor de producción:
 *   php migrar_ccartera_final.php
 * 
 * Migra: Clientes, Créditos, Pagos, Negocio, Análisis Económico,
 *        Observaciones, Zonas, Tipos de Crédito, Refinanciamientos
 * Solo: SedeID=1 (Chiclayo, idsucursal='01')
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

set_time_limit(0);
ini_set('memory_limit', '512M');

$SEDE_ID   = 1;
$SEDE_OLD  = '01';
$CHUNK     = 500;

echo "╔══════════════════════════════════════════╗\n";
echo "║  MIGRACIÓN COMPLETA CCARTERA → JALUD     ║\n";
echo "║  Chiclayo (SedeID=1)                     ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

$limpiarDNI = fn($s) => preg_replace('/[^0-9]/', '', trim($s ?? ''));

// ============================================================
// PASO 1: CLIENTES
// ============================================================
echo "=== PASO 1/8: Clientes ===\n";
$oldClientes = DB::connection('ccartera')->table('clientes')->where('idsucursal', $SEDE_OLD)->get();
echo "CCARTERA: " . count($oldClientes) . " clientes\n";

$dniToNewId = []; $creados = 0; $matcheados = 0; $sinDni = 0;

DB::beginTransaction();
foreach ($oldClientes as $oc) {
    $dni = $limpiarDNI($oc->ruc__cli);
    $nombre = trim($oc->raz__soc ?? '');
    if (empty($dni)) { $sinDni++; continue; }
    
    $existente = DB::table('Cliente')->where('DNI', $dni)->where('Activo', 1)->first();
    if ($existente) { $dniToNewId[$dni] = $existente->ClienteID; $matcheados++; continue; }
    
    $partes = explode(' ', $nombre, 3);
    $apePat = $partes[0] ?? ''; $apeMat = $partes[1] ?? ''; $nomb = $partes[2] ?? '';
    if (empty($nomb) && !empty($apeMat)) { $nomb = $apeMat; $apeMat = ''; }
    
    $nuevoId = DB::table('Cliente')->insertGetId([
        'DNI' => $dni, 'NombresApellidos' => strtoupper($nombre),
        'ApellidoPaterno' => strtoupper($apePat), 'ApellidoMaterno' => strtoupper($apeMat),
        'Nombres' => strtoupper($nomb), 'Sexo' => 'M', 'Estado' => 'NO OBSERVADO',
        'Domicilio' => trim($oc->dir__cli ?? ''), 'Activo' => 1, 'SedeID' => $SEDE_ID,
        'ConyugeNombresApellidos' => trim($oc->nombconyu ?? ''),
        'ConyugeDNI' => trim($oc->dniconyu ?? ''),
        'FechaRegistro' => $oc->fechaingreso ?? now(),
    ]);
    $dniToNewId[$dni] = $nuevoId; $creados++;
}
DB::commit();
unset($oldClientes);
echo "  Matcheados: {$matcheados} | Creados: {$creados} | Sin DNI: {$sinDni}\n\n";

// ============================================================
// PASO 2: CRÉDITOS (totales ↔ propues)
// ============================================================
echo "=== PASO 2/8: Créditos ===\n";

// Cargar propues
$propuesMap = [];
$propsCC = DB::connection('ccartera')->table('propues')->where('idsucursal', $SEDE_OLD)->get();
foreach ($propsCC as $p) $propuesMap[trim($p->nro)] = $p;

// Cargar totales
$totalesList = [];
$ts = DB::connection('ccartera')->table('totales')->where('idsucursal', $SEDE_OLD)->get();
foreach ($ts as $t) {
    $key = $limpiarDNI($t->ruc__clpr) . '|' . round((float)$t->total, 2);
    if (!isset($totalesList[$key])) $totalesList[$key] = [];
    $totalesList[$key][] = $t;
}
echo "totales: " . count($ts) . " | propues: " . count($propuesMap) . " | claves tot: " . count($totalesList) . "\n";

// Mapeo tipcre → TipoCreditoID (crear faltantes)
$tipcreToTC = [];
$existingTC = DB::table('TipoCredito')->where('SedeID', $SEDE_ID)->pluck('TipoCreditoID', 'Codigo')->toArray();
$tipcreTable = DB::connection('ccartera')->table('tipcre')->get();
foreach ($tipcreTable as $tc) {
    $cod = 'C' . trim($tc->tipcre);
    if (!isset($existingTC[$cod])) {
        $existingTC[$cod] = DB::table('TipoCredito')->insertGetId([
            'Codigo' => $cod, 'Descripcion' => trim($tc->detcre), 'Activo' => 1,
            'SedeID' => $SEDE_ID, 'FechaCreacion' => now(),
        ]);
    }
    $tipcreToTC[trim($tc->tipcre)] = $existingTC[$cod];
}

// Tasas
$tasas = DB::table('Tasa')->where('SedeID', $SEDE_ID)->where('Activo', 1)->get();
$findTasa = function($rate) use ($tasas) {
    $best = 1; $bd = PHP_FLOAT_MAX;
    foreach ($tasas as $t) { $d = abs((float)$t->Valor - $rate); if ($d < $bd) { $bd = $d; $best = $t->TasaID; } }
    return $best;
};

$ultimoCodigo = DB::table('ProposicionCredito')->where('CodigoCredito', 'LIKE', 'C-%')
    ->orderBy('ProposicionCreditoID', 'desc')->value('CodigoCredito');
$seq = $ultimoCodigo ? intval(substr($ultimoCodigo, 2)) + 1 : 1;

$mapaNumOpeToCredito = []; $mapaNumOpeToProp = [];
$credCreados = 0; $credSinCliente = 0; $refi991 = 0;

DB::beginTransaction();
foreach ($totalesList as $key => $records) {
    foreach ($records as $t) {
        $dniCli = $limpiarDNI($t->ruc__clpr);
        $clienteId = $dniToNewId[$dniCli] ?? null;
        if (!$clienteId) { $credSinCliente++; continue; }
        
        $nro = trim($t->nro);
        $prop = $propuesMap[$nro] ?? null;
        $totalT = (float)$t->total;
        $montoPropues = $prop ? (float)$prop->monto : $totalT;
        if ($montoPropues <= 0) $montoPropues = $totalT;
        
        $tipcre = trim($t->tipcre);
        $tipoCreditoID = $tipcreToTC[$tipcre] ?? 1;
        $esRefi = ($tipcre === '991') ? 1 : 0;
        
        $montoCapital = $montoPropues;
        $montoInteres = max(0, $totalT - $montoCapital);
        $tasaInteres = $montoCapital > 0 ? round($montoInteres / $montoCapital * 100, 2) : 0;
        $tasaID = $findTasa($tasaInteres);
        $cuota = (float)$t->cuota;
        $nroCuotas = $cuota > 0 ? (int)ceil($totalT / $cuota) : 1;
        if ($nroCuotas < 1) $nroCuotas = 1;
        
        $fecha = $prop ? ($prop->fecha ?? $t->fec__ven ?? now()) : ($t->fec__ven ?? now());
        $fecVen = $t->fec__ven ? Carbon::parse($t->fec__ven) : Carbon::parse($fecha)->addDays(30);
        $pagada = (bool)($t->pagada ?? false);
        
        $codigo = 'C-' . str_pad($seq, 6, '0', STR_PAD_LEFT); $seq++;
        
        $propId = DB::table('ProposicionCredito')->insertGetId([
            'CodigoCredito' => $codigo, 'ClienteID' => $clienteId, 'CodigoCliente' => $dniCli,
            'TipoCreditoID' => $tipoCreditoID, 'MontoTotal' => $montoCapital, 'TasaID' => $tasaID,
            'TasaInteres' => $tasaInteres, 'Plazo' => max(1, (int)$t->dias),
            'NumeroCuotas' => $nroCuotas, 'MontoCuota' => $cuota > 0 ? $cuota : $totalT,
            'MontoInteres' => $montoInteres, 'TasaMora' => (float)$t->mora,
            'MontoTotalPagar' => $totalT, 'SaldoPendiente' => (float)$t->saldo,
            'FechaPropuesta' => $fecha, 'Estado' => 'APROBADO', 'Activo' => 1,
            'SedeID' => $SEDE_ID, 'EsRefinanciamiento' => $esRefi,
            'FechaAprobacion' => $fecha, 'UserProponenteID' => 1,
        ]);
        
        $creditoId = DB::table('Credito')->insertGetId([
            'ProposicionCreditoID' => $propId, 'TipoPagoID' => 1,
            'FechaGeneracion' => $fecha, 'FechaInicio' => Carbon::parse($fecha)->toDateString(),
            'FechaVencimiento' => $fecVen->toDateString(),
            'EstatusCreditoFinal' => $pagada ? 'SALDADO' : 'ACTIVO', 'Activo' => 1,
            'SedeID' => $SEDE_ID, 'UserGeneracionID' => 1,
        ]);
        if ($pagada && $t->fec__pag) {
            DB::table('Credito')->where('CreditoID', $creditoId)
                ->update(['FechaSaldamiento' => Carbon::parse($t->fec__pag)]);
        }
        
        DB::table('cuota')->insert([
            'CreditoID' => $creditoId, 'NumeroCuota' => 1,
            'FechaVencimiento' => $fecVen, 'MontoCuota' => $totalT,
            'Estado' => $pagada ? 'PAGADA' : 'PENDIENTE', 'Activo' => 1,
            'SedeID' => $SEDE_ID, 'FechaCreacion' => $fecha,
        ]);
        if ($pagada) {
            DB::table('cuota')->where('CreditoID', $creditoId)->update(['FechaPago' => $t->fec__pag ?? now()]);
        }
        
        $mapaNumOpeToCredito[trim($t->num__ope)] = $creditoId;
        $mapaNumOpeToProp[trim($t->num__ope)] = $propId;
        $credCreados++;
        if ($esRefi) $refi991++;
    }
}
DB::commit();
echo "  Creados: {$credCreados} | Refi(991): {$refi991} | Sin cliente: {$credSinCliente}\n\n";

// ============================================================
// PASO 3: PAGOS (chunked, con fecha2)
// ============================================================
echo "=== PASO 3/8: Pagos ===\n";
$cuotaCache = [];
foreach ($mapaNumOpeToCredito as $cid) {
    $cuotaCache[$cid] = DB::table('cuota')->where('CreditoID', $cid)->where('Activo', 1)->value('CuotaID');
}

$totalPagosCC = DB::connection('ccartera')->table('pagos')->where('idsucursal', $SEDE_OLD)->count();
echo "CCARTERA: {$totalPagosCC} pagos. Procesando lotes de {$CHUNK}...\n";

$pagosCreados = 0; $pagosSinMatch = 0; $loteNum = 0;

DB::connection('ccartera')->table('pagos')->where('idsucursal', $SEDE_OLD)->orderBy('id')
    ->chunk($CHUNK, function ($lote) use ($SEDE_ID, $CHUNK, $mapaNumOpeToCredito, $cuotaCache, &$pagosCreados, &$pagosSinMatch, &$loteNum, $totalPagosCC) {
    DB::beginTransaction();
    foreach ($lote as $op) {
        $numOpe = trim($op->num__ope ?? '');
        $creditoId = $mapaNumOpeToCredito[$numOpe] ?? null;
        if (!$creditoId) { $pagosSinMatch++; continue; }
        
        $tipoConcepto = 'C'; $tp = trim($op->tipo ?? 'P');
        if ($tp === 'M') $tipoConcepto = 'M'; elseif ($tp === 'I') $tipoConcepto = 'I';
        
        DB::table('pago')->insert([
            'CreditoID' => $creditoId, 'CuotaID' => $cuotaCache[$creditoId] ?? null,
            'MontoPagado' => (float)($op->monto ?? 0),
            'FechaPago' => Carbon::parse($op->fecha ?? now())->format('Y-m-d'),
            'TipoPago' => 'EFECTIVO', 'TipoConcepto' => $tipoConcepto,
            'Activo' => 1, 'SedeID' => $SEDE_ID,
            'FechaCreacion' => $op->fecha2 ?? $op->fecha ?? now(),
        ]);
        $pagosCreados++;
    }
    DB::commit();
    $loteNum++;
    if ($loteNum % 25 == 0) {
        $pct = round($loteNum * $CHUNK / max($totalPagosCC, 1) * 100);
        echo "  Lote {$loteNum}: {$pagosCreados} pagos ({$pct}%)\n";
    }
});
echo "  Creados: {$pagosCreados} | Sin match: {$pagosSinMatch}\n\n";

// ============================================================
// PASO 4: SALDOS
// ============================================================
echo "=== PASO 4/8: Saldos ===\n";
$saldados = 0; $fueRefinanciadaAjustados = 0;

DB::beginTransaction();
foreach ($mapaNumOpeToProp as $numOpe => $propId) {
    $creditoId = $mapaNumOpeToCredito[$numOpe] ?? null;
    if (!$creditoId) continue;
    
    $totalPagado = DB::table('pago')->where('CreditoID', $creditoId)->where('Activo', 1)->sum('MontoPagado');
    $prop = DB::table('ProposicionCredito')->where('ProposicionCreditoID', $propId)->first();
    if (!$prop) continue;
    
    $saldo = max(0, (float)$prop->MontoTotalPagar - $totalPagado);
    DB::table('ProposicionCredito')->where('ProposicionCreditoID', $propId)->update(['SaldoPendiente' => $saldo]);
    
    if ($saldo <= 0 && $totalPagado > 0) {
        DB::table('Credito')->where('ProposicionCreditoID', $propId)->update([
            'EstatusCreditoFinal' => 'SALDADO', 'FechaSaldamiento' => now(),
        ]);
        DB::table('cuota')->where('CreditoID', $creditoId)->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
        $saldados++;
    }
}
DB::commit();
echo "  Saldados: {$saldados}\n\n";

// ============================================================
// PASO 5: NEGOCIO (Ciudad + Zona desde dir__cli)
// ============================================================
echo "=== PASO 5/8: Negocio (Ciudad + Zona) ===\n";

// Extraer zona de dir__cli
$zonaPorDNI = [];
$ccDirs = DB::connection('ccartera')->table('clientes')->where('idsucursal', $SEDE_OLD)->get(['ruc__cli', 'dir__cli']);
foreach ($ccDirs as $c) {
    $dni = $limpiarDNI($c->ruc__cli);
    $dir = trim($c->dir__cli ?? '');
    if (preg_match('/(?:CHICLAYO|CHICALAYO)\s*[-]?\s*(\d)/i', $dir, $m)) {
        $zn = (int)$m[1];
        if ($zn >= 1 && $zn <= 3) $zonaPorDNI[$dni] = $zn;
    }
}
echo "DNIs con zona detectada: " . count($zonaPorDNI) . "\n";

// Clientes sin Negocio
$clientesSinNegocio = DB::table('Cliente as c')
    ->leftJoin('Negocio as n', 'c.ClienteID', '=', 'n.ClienteID')
    ->where('c.SedeID', $SEDE_ID)->where('c.Activo', 1)
    ->whereNull('n.NegocioID')
    ->get(['c.ClienteID', 'c.DNI', 'c.Domicilio']);

echo "Clientes sin Negocio: " . count($clientesSinNegocio) . "\n";

$negociosCreados = 0; $conZona = 0;
DB::beginTransaction();
foreach ($clientesSinNegocio as $c) {
    $dni = $limpiarDNI($c->DNI);
    $zonaID = $zonaPorDNI[$dni] ?? null;
    DB::table('Negocio')->insert([
        'ClienteID' => $c->ClienteID, 'CiudadID' => 1, 'ZonaID' => $zonaID,
        'DireccionNegocio' => $c->Domicilio, 'Activo' => 1, 'SedeID' => $SEDE_ID,
    ]);
    $negociosCreados++;
    if ($zonaID) $conZona++;
}
DB::commit();

// Actualizar ZonaID en Negocios existentes que tengan NULL
DB::statement("UPDATE Negocio n INNER JOIN (SELECT p.ClienteID, MAX(p.ZonaID) as ZonaID FROM ProposicionCredito p WHERE p.SedeID={$SEDE_ID} AND p.ZonaID IS NOT NULL GROUP BY p.ClienteID) p ON n.ClienteID = p.ClienteID SET n.ZonaID = p.ZonaID WHERE n.SedeID={$SEDE_ID} AND n.ZonaID IS NULL");

echo "  Negocios creados: {$negociosCreados} | Con zona: {$conZona}\n\n";

// Zona en ProposicionCredito desde dir__cli
$propsSinZona = DB::table('ProposicionCredito')->where('SedeID', $SEDE_ID)->where('Activo', 1)->whereNull('ZonaID')->get(['ProposicionCreditoID', 'CodigoCliente']);
$zonasProp = 0;
DB::beginTransaction();
foreach ($propsSinZona as $p) {
    $dni = $limpiarDNI($p->CodigoCliente);
    if (isset($zonaPorDNI[$dni])) {
        DB::table('ProposicionCredito')->where('ProposicionCreditoID', $p->ProposicionCreditoID)
            ->update(['ZonaID' => $zonaPorDNI[$dni]]);
        $zonasProp++;
    }
}
DB::commit();
echo "  Zonas en ProposicionCredito desde dir__cli: {$zonasProp}\n\n";

// ============================================================
// PASO 6: ANÁLISIS ECONÓMICO
// ============================================================
echo "=== PASO 6/8: Análisis Económico ===\n";

$ccData = [];
$ccFull = DB::connection('ccartera')->table('clientes')->where('idsucursal', $SEDE_OLD)->get();
foreach ($ccFull as $c) {
    $ccData[$limpiarDNI($c->ruc__cli)] = $c;
}

$sinAE = DB::table('Cliente as c')
    ->leftJoin('AnalisisEconomico as ae', 'c.ClienteID', '=', 'ae.ClienteID')
    ->where('c.SedeID', $SEDE_ID)->where('c.Activo', 1)
    ->whereNull('ae.AnalisisEconomicoID')
    ->get(['c.ClienteID', 'c.DNI']);
echo "Clientes sin AE: " . count($sinAE) . "\n";

$aeCreados = 0;
DB::beginTransaction();
foreach ($sinAE as $c) {
    $dni = $limpiarDNI($c->DNI);
    $cc = $ccData[$dni] ?? null;
    if (!$cc) continue;
    
    $capMan = round((float)($cc->capcliente ?? 0), 2);
    $capEst = round((float)($cc->capestimado ?? 0), 2);
    $vtaMin = round((float)($cc->vmanif1 ?? 0), 2);
    $vtaMax = round((float)($cc->vmanif2 ?? 0), 2);
    $vtaEst = round((float)($cc->vestimada ?? 0), 2);
    $mmr    = round((float)($cc->mmrjefeoficina ?? 0), 2);
    
    // Saltar si TODO es 0
    if ($capMan == 0 && $capEst == 0 && $vtaMin == 0 && $vtaMax == 0 && $vtaEst == 0 && $mmr == 0) continue;
    
    $fechaIngreso = $cc->fechaingreso;
    if (!$fechaIngreso || Carbon::parse($fechaIngreso)->year < 2000) $fechaIngreso = now();
    
    DB::table('AnalisisEconomico')->insert([
        'ClienteID' => $c->ClienteID,
        'CapitalManifestado' => $capMan, 'CapitalEstimado' => $capEst,
        'VentaManifestadaMin' => $vtaMin, 'VentaManifestadaMax' => $vtaMax,
        'VentaEstimada' => $vtaEst, 'MontoMaxRecomendado' => $mmr,
        'FechaAnalisis' => $fechaIngreso, 'UsuarioAnalisis' => trim($cc->usermodi ?? ''),
        'Activo' => 1, 'SedeID' => $SEDE_ID,
    ]);
    $aeCreados++;
}
DB::commit();
echo "  Creados: {$aeCreados}\n\n";

// ============================================================
// PASO 7: OBSERVACIONES
// ============================================================
echo "=== PASO 7/8: Observaciones ===\n";
$ccObs = DB::connection('ccartera')->select(
    "SELECT ruc__cli, observaciones FROM clientes WHERE idsucursal='{$SEDE_OLD}' AND DATALENGTH(observaciones) > 0"
);
$obsPorDNI = [];
foreach ($ccObs as $o) {
    $obs = trim($o->observaciones);
    if (!empty($obs)) $obsPorDNI[$limpiarDNI($o->ruc__cli)] = $obs;
}
echo "Observaciones CCARTERA: " . count($obsPorDNI) . "\n";

$obsAct = 0;
DB::beginTransaction();
$clientes = DB::table('Cliente')->where('SedeID', $SEDE_ID)->where('Activo', 1)->get(['ClienteID', 'DNI']);
foreach ($clientes as $c) {
    $dni = $limpiarDNI($c->DNI);
    if (isset($obsPorDNI[$dni])) {
        DB::table('Cliente')->where('ClienteID', $c->ClienteID)->update(['Observaciones' => $obsPorDNI[$dni]]);
        $obsAct++;
    }
}
DB::commit();
echo "  Actualizados: {$obsAct}\n\n";

// ============================================================
// PASO 8: REFINANCIAMIENTO (FueRefinanciada=1 y saldo 0)
// ============================================================
echo "=== PASO 8/8: Ajustes finales ===\n";

// Vincular EsRefinanciamiento → FueRefinanciada en el crédito anterior
$refis = DB::table('ProposicionCredito')->where('SedeID', $SEDE_ID)->where('EsRefinanciamiento', 1)->get();
$fueRefiAct = 0;
DB::beginTransaction();
foreach ($refis as $r) {
    // Obtener fecha del crédito refinanciamiento
    $fechaRefi = DB::table('Credito')->where('ProposicionCreditoID', $r->ProposicionCreditoID)->value('FechaGeneracion');
    if (!$fechaRefi) continue;

    // Buscar crédito anterior del mismo cliente con fecha anterior
    $anterior = DB::table('ProposicionCredito as p2')
        ->join('Credito as c2', 'p2.ProposicionCreditoID', '=', 'c2.ProposicionCreditoID')
        ->where('p2.SedeID', $SEDE_ID)->where('p2.ClienteID', $r->ClienteID)
        ->where('p2.ProposicionCreditoID', '!=', $r->ProposicionCreditoID)
        ->where('c2.FechaGeneracion', '<', $fechaRefi)
        ->orderByDesc('c2.FechaGeneracion')
        ->first(['p2.ProposicionCreditoID']);
    
    if ($anterior) {
        DB::table('ProposicionCredito')->where('ProposicionCreditoID', $anterior->ProposicionCreditoID)
            ->update(['FueRefinanciada' => 1]);
        DB::table('ProposicionCredito')->where('ProposicionCreditoID', $r->ProposicionCreditoID)
            ->update(['ProposicionCreditoAnteriorID' => $anterior->ProposicionCreditoID]);
        $fueRefiAct++;
    }
}
DB::commit();
echo "  FueRefinanciada=1 establecidos: {$fueRefiAct}\n";

// Saldo=0 para FueRefinanciada
$ajusteSaldo = DB::table('ProposicionCredito')->where('SedeID', $SEDE_ID)
    ->where('FueRefinanciada', 1)->where('SaldoPendiente', '>', 0)
    ->update(['SaldoPendiente' => 0]);
echo "  Saldos ajustados a 0 (FueRefinanciada): {$ajusteSaldo}\n\n";

// ============================================================
// RESUMEN FINAL
// ============================================================
echo "╔═══════════════════════════════╗\n";
echo "║       RESUMEN FINAL           ║\n";
echo "╠═══════════════════════════════╣\n";
printf("║ Clientes creados:     %5d ║\n", $creados);
printf("║ Clientes matcheados:  %5d ║\n", $matcheados);
printf("║ Créditos creados:     %5d ║\n", $credCreados);
printf("║ Refinanciados (991):  %5d ║\n", $refi991);
printf("║ Pagos migrados:       %5d ║\n", $pagosCreados);
printf("║ Créditos saldados:    %5d ║\n", $saldados);
printf("║ Negocios creados:     %5d ║\n", $negociosCreados);
printf("║ Análisis Económico:   %5d ║\n", $aeCreados);
printf("║ Observaciones act.:   %5d ║\n", $obsAct);
echo "╚═══════════════════════════════╝\n";
echo "\nDone.\n";
