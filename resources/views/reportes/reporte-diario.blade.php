<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Reporte General del Día {{ $fecha->format('d/m/Y') }}</title>
    <style>
        @page {
            margin: 10mm 8mm 10mm 8mm;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5px;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .header {
            width: 100%;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border: none;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .header-left {
            text-align: left;
            font-weight: bold;
            font-size: 11px;
        }

        .header-right {
            text-align: right;
            font-size: 8.5px;
        }

        .titulo {
            text-align: center;
            margin: 15px 0 5px 0;
            font-size: 11px;
            font-weight: bold;
        }

        .titulo-separador {
            text-align: center;
            margin-bottom: 15px;
        }

        .seccion-titulo {
            font-weight: bold;
            font-size: 11px;
            margin-top: 10px;
            margin-bottom: 2px;
        }

        .seccion-subrayado {
            margin-bottom: 5px;
        }

        .datos-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8px;
        }

        .datos-table th {
            border: none;
            padding: 3px 2px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .datos-table td {
            border: none;
            padding: 2px 2px;
            font-size: 8px;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .datos-table .monto {
            text-align: right;
            padding-right: 5px;
        }

        .linea-separadora-doble {
            border: none;
            border-top: 1px solid #000;
            margin: 1px 0;
        }

        .total-row {
            font-weight: bold;
            font-size: 9px;
        }

        .total-row td {
            padding-top: 5px;
            border-top: 1px solid #000;
        }

        .seccion-separador {
            margin: 15px 0 8px 0;
        }
    </style>
</head>

<body>

    {{-- CABECERA --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left" style="width: 50%;">
                    JALUD SAC<br>
                    &nbsp;&nbsp;{{ $sedeNombre }}
                </td>
                <td class="header-right" style="width: 50%;">
                    Pagina : 001<br>
                    Emision: {{ $emision->format('d/m/Y') }}<br>
                    Hora&nbsp;&nbsp;&nbsp;: {{ $emision->format('H:i:s') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="titulo">
        REPORTE GENERAL DEL DIA {{ $fecha->format('d/m/Y') }}
    </div>
    <div class="titulo-separador">
        ----------------------------------------
    </div>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 1. PAGOS REALIZADOS --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-titulo">&nbsp;PAGOS REALIZADOS</div>
    <div class="seccion-subrayado">&nbsp;================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 11%;">OPERACION</th>
                <th style="width: 12%;">ZONA</th>
                <th style="width: 7%;">CRED.</th>
                <th style="width: 34%;">CLIENTE</th>
                <th style="width: 16%;">TIPO</th>
                <th style="width: 20%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->CodigoCredito ?? '' }}</td>
                    <td>{{ mb_strtoupper($pago->ZonaNombre ?? 'N/A') }}</td>
                    <td>{{ $pago->TipoCreditoCodigo ?? '001' }}</td>
                    <td>{{ mb_strtoupper($pago->NombresApellidos ?? '') }}</td>
                    <td>
                        @php
                            $tipoPago = match($pago->TipoPago ?? '') {
                                'EFECTIVO' => 'Efectivo',
                                'YAPE_PLIN' => 'Yape/Plin',
                                'TRANSFERENCIA', 'TRANSFERENCIA_BANCARIA' => 'Transferencia',
                                default => $pago->TipoPago ?? '-'
                            };
                        @endphp
                        {{ $tipoPago }}
                    </td>
                    <td class="monto">{{ number_format($pago->MontoOriginal ?? $pago->MontoPagado, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">Sin pagos registrados</td>
                </tr>
            @endforelse

            @if($pagos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL DE PAGOS:</td>
                    <td class="monto">{{ number_format($totalAmortizaciones, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 2. CREDITOS EMITIDOS --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;CREDITOS EMITIDOS</div>
    <div class="seccion-subrayado">&nbsp;==================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 15%;">OPERACION</th>
                <th style="width: 8%;">CREDITO</th>
                <th style="width: 44%;">CLIENTE</th>
                <th style="width: 11%; text-align: right;">CAPITAL</th>
                <th style="width: 11%; text-align: right;">INTERES</th>
                <th style="width: 11%; text-align: right; padding-right: 5px;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditos as $credito)
                <tr>
                    <td>{{ $credito->CodigoCredito ?? '' }}</td>
                    <td>{{ $credito->TipoCreditoCodigo ?? '001' }}</td>
                    <td>{{ mb_strtoupper($credito->NombresApellidos ?? '') }}</td>
                    <td class="monto">{{ number_format($credito->MontoTotal, 2) }}</td>
                    <td class="monto">{{ number_format($credito->MontoInteres, 2) }}</td>
                    <td class="monto">
                        {{ number_format($credito->MontoTotalPagar ?? ($credito->MontoTotal + $credito->MontoInteres), 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">Sin créditos emitidos</td>
                </tr>
            @endforelse

            @if($creditos->count() > 0)
                    <tr class="total-row">
                        <td></td>
                        <td></td>
                        <td style="text-align: right; padding-right: 10px;">TOTAL CREDITOS EMITIDOS:</td>
                        <td class="monto">{{ number_format($totalCreditosEmitidos, 2) }}</td>
                        <td class="monto">{{ number_format($creditos->sum('MontoInteres'), 2) }}</td>
                        <td class="monto">
                            {{ number_format($creditos->sum(function ($c) {
                return $c->MontoTotalPagar ?? ($c->MontoTotal + $c->MontoInteres); }), 2) }}
                        </td>
                    </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 3. EXCEDENTES --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;EXCEDENTES</div>
    <div class="seccion-subrayado">&nbsp;==========</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 14%;">NRO. OP.</th>
                <th style="width: 14%;">FECHA</th>
                <th style="width: 16%;">TIPO</th>
                <th style="width: 28%;">REGULARIZADO A</th>
                <th style="width: 14%; text-align: right;">MONTO</th>
                <th style="width: 14%; text-align: center;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalExcedentesTabla = 0;
                $excedentesConDatos = 0;
            @endphp

            @forelse($excedentesDia as $exc)
                @php
                    $excedentesConDatos++;
                    $montoOriginalEx = (float)$exc->Monto + (float)$exc->resoluciones->sum('MontoAplicar');
                    $totalExcedentesTabla += $montoOriginalEx;
                    $resolucion = $exc->resoluciones->first();
                    $regularizadoA = '';
                    if ($resolucion) {
                        $cred = $resolucion->creditoDestino;
                        $cli = $resolucion->clienteDestino;
                        if ($cred && $cred->proposicion) {
                            $regularizadoA = ($cred->proposicion->CodigoCredito ?? '') . ' - ' . mb_strtoupper($cred->proposicion->cliente->NombresApellidos ?? '');
                        } elseif ($cli) {
                            $regularizadoA = mb_strtoupper($cli->NombresApellidos ?? '');
                        }
                    }
                    $estadoExc = $exc->EstadoResolucion === 'RESUELTO' ? 'APLICADO' : $exc->EstadoResolucion;
                    $tipoExc = match($exc->TipoExcedente) {
                        'YAPE_TRANSFERENCIA' => 'Yape/Transfer.',
                        'SOBRANTE_PROMOTOR' => 'Sobr. Promotor',
                        'SOBRANTE_CAJERO' => 'Exced. Oficina',
                        default => $exc->TipoExcedente,
                    };
                @endphp
                <tr>
                    <td>{{ $exc->NroOperacion ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($exc->Fecha)->format('d/m/Y') }}</td>
                    <td>{{ $tipoExc }}</td>
                    <td>{{ $regularizadoA ?: '—' }}</td>
                    <td class="monto">{{ number_format($montoOriginalEx, 2) }}</td>
                    <td style="text-align: center;">{{ $estadoExc }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">Sin excedentes registrados</td>
                </tr>
            @endforelse

            @if($excedentesConDatos > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL EXCEDENTES:</td>
                    <td class="monto">{{ number_format($totalExcedentesTabla, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 4A. INGRESO DE REMESAS --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;INGRESO DE REMESAS</div>
    <div class="seccion-subrayado">&nbsp;===================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 10%;">NRO.</th>
                <th style="width: 14%;">FECHA</th>
                <th style="width: 28%;">SEDE ORIGEN</th>
                <th style="width: 28%;">CUENTA</th>
                <th style="width: 20%; text-align: right;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ingresosRemesas as $rem)
                <tr>
                    <td>{{ $rem->TransferenciaID }}</td>
                    <td>{{ ($rem->FechaRespuesta ?? $rem->FechaTransferencia) ? \Carbon\Carbon::parse($rem->FechaRespuesta ?? $rem->FechaTransferencia)->format('d/m/Y') : '' }}</td>
                    <td>{{ mb_strtoupper($rem->sedeOrigen?->Nombre ?? 'N/A') }}</td>
                    <td>{{ $rem->CuentaDestino ?? 'CAJA_ABIERTA' }}</td>
                    <td class="monto">{{ number_format($rem->Monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">Sin ingresos de remesas</td>
                </tr>
            @endforelse

            @if($ingresosRemesas->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL INGRESOS:</td>
                    <td class="monto">{{ number_format($totalIngresosRemesas, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 4B. SALIDA DE REMESAS --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;SALIDA DE REMESAS</div>
    <div class="seccion-subrayado">&nbsp;=================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 10%;">NRO.</th>
                <th style="width: 14%;">FECHA</th>
                <th style="width: 28%;">SEDE DESTINO</th>
                <th style="width: 28%;">CUENTA</th>
                <th style="width: 20%; text-align: right;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($salidasRemesas as $rem)
                <tr>
                    <td>{{ $rem->TransferenciaID }}</td>
                    <td>{{ ($rem->FechaRespuesta ?? $rem->FechaTransferencia) ? \Carbon\Carbon::parse($rem->FechaRespuesta ?? $rem->FechaTransferencia)->format('d/m/Y') : '' }}</td>
                    <td>{{ mb_strtoupper($rem->sedeDestino?->Nombre ?? 'N/A') }}</td>
                    <td>{{ $rem->CuentaOrigen ?? 'CAJA_ABIERTA' }}</td>
                    <td class="monto">{{ number_format($rem->Monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">Sin salidas de remesas</td>
                </tr>
            @endforelse

            @if($salidasRemesas->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL SALIDAS:</td>
                    <td class="monto">{{ number_format($totalSalidasRemesas, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 5. EXTORNOS Y DEVOLUCIONES --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;EXTORNOS Y DEVOLUCIONES</div>
    <div class="seccion-subrayado">&nbsp;========================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 15%;">OPERACION</th>
                <th style="width: 12%;">FECHA</th>
                <th style="width: 35%;">CTA CLIENTE</th>
                <th style="width: 18%; text-align: right;">MONTO</th>
                <th style="width: 20%; text-align: center;">TIPO</th>
            </tr>
        </thead>
        <tbody>
            @php
                $filaNum = 0;
                $totalExtornosDev = 0;
            @endphp

            @foreach($extornos as $extorno)
                @php
                    $filaNum++;
                    $totalExtornosDev += $extorno->MontoAplicar;
                    $clienteNombre = $extorno->creditoDestino?->proposicion?->cliente?->NombresApellidos
                        ?? $extorno->clienteDestino?->NombresApellidos
                        ?? $extorno->clienteOrigen?->NombresApellidos
                        ?? 'N/A';
                    $codigoCredito = $extorno->creditoDestino?->proposicion?->CodigoCredito ?? '';
                    $ctaCliente = $codigoCredito ? "{$codigoCredito} - " . mb_strtoupper($clienteNombre) : mb_strtoupper($clienteNombre);
                @endphp
                <tr>
                    <td>{{ $extorno->excedente?->NroOperacion ?? '' }}</td>
                    <td>{{ \Carbon\Carbon::parse($extorno->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $ctaCliente }}</td>
                    <td class="monto">{{ number_format($extorno->MontoAplicar, 2) }}</td>
                    <td style="text-align: center;">EXT</td>
                </tr>
            @endforeach

            @foreach($exoneraciones as $exoneracion)
                @php
                    $filaNum++;
                    $totalExtornosDev += $exoneracion->MontoExonerado;
                    $clienteNombreExo = $exoneracion->credito?->proposicion?->cliente?->NombresApellidos ?? '';
                    $codigoCreditoExo = $exoneracion->credito?->proposicion?->CodigoCredito ?? '';
                    $ctaClienteExo = $codigoCreditoExo ? "{$codigoCreditoExo} - " . mb_strtoupper($clienteNombreExo) : mb_strtoupper($clienteNombreExo);
                @endphp
                <tr>
                    <td>{{ $codigoCreditoExo }}</td>
                    <td>{{ $exoneracion->FechaAprobacion ? \Carbon\Carbon::parse($exoneracion->FechaAprobacion)->format('d/m/Y') : '' }}
                    </td>
                    <td>{{ $ctaClienteExo }}</td>
                    <td class="monto">{{ number_format($exoneracion->MontoExonerado, 2) }}</td>
                    <td style="text-align: center;">EXO</td>
                </tr>
            @endforeach

            @if($filaNum === 0)
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">Sin extornos / devoluciones</td>
                </tr>
            @endif

            @if($filaNum > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL EXTORNOS Y DEV.:</td>
                    <td class="monto">{{ number_format($totalExtornosDev, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 6A. GASTOS CAJA CHICA --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;GASTOS CAJA CHICA</div>
    <div class="seccion-subrayado">&nbsp;=================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 10%;">NRO.</th>
                <th style="width: 12%;">FECHA</th>
                <th style="width: 28%;">PROVEEDOR / MOTIVO</th>
                <th style="width: 30%;">OBSERVACION</th>
                <th style="width: 20%; text-align: right;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCajaChicaDetalle = 0;
                $filasCajaChica = 0;
            @endphp

            @foreach($gastos as $gasto)
                @php
                    $filasCajaChica++;
                    $totalCajaChicaDetalle += $gasto->Total;
                    $proveedorG = $gasto->proveedor?->Nombre ?? ($gasto->proveedor?->RazonSocial ?? '');
                    $motivoG = $gasto->motivo?->Descripcion ?? '';
                    $refG = $proveedorG ?: $motivoG ?: 'Gasto #' . $gasto->GastoID;
                @endphp
                <tr>
                    <td>G-{{ $gasto->GastoID }}</td>
                    <td>{{ \Carbon\Carbon::parse($gasto->FechaEmision)->format('d/m/Y') }}</td>
                    <td>{{ mb_strtoupper($refG) }}</td>
                    <td>{{ $gasto->Observaciones ?? '' }}</td>
                    <td class="monto">{{ number_format($gasto->Total, 2) }}</td>
                </tr>
            @endforeach

            @foreach($compras as $compra)
                @php
                    $filasCajaChica++;
                    $totalCajaChicaDetalle += $compra->Total;
                    $proveedorC = $compra->proveedor?->Nombre ?? ($compra->proveedor?->RazonSocial ?? '');
                    $refC = $proveedorC ?: 'Compra #' . $compra->CompraID;
                @endphp
                <tr>
                    <td>C-{{ $compra->CompraID }}</td>
                    <td>{{ \Carbon\Carbon::parse($compra->FechaEmision)->format('d/m/Y') }}</td>
                    <td>{{ mb_strtoupper($refC) }}</td>
                    <td>{{ $compra->Observaciones ?? '' }}</td>
                    <td class="monto">{{ number_format($compra->Total, 2) }}</td>
                </tr>
            @endforeach

            @if($filasCajaChica === 0)
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">Sin gastos de caja chica</td>
                </tr>
            @endif

            @if($filasCajaChica > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL CAJA CHICA:</td>
                    <td class="monto">{{ number_format($totalCajaChicaDetalle, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 7. BALANCE DE CAJA --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

    <div class="seccion-titulo" style="text-align: center; font-size: 10px;">&nbsp;BALANCE DE CAJA</div>
    <div class="seccion-subrayado" style="text-align: center;">&nbsp;==================</div>

    <table class="datos-table" style="margin-top: 5px;">
        <tr>
            {{-- COLUMNA IZQUIERDA: CAJA ABIERTA --}}
            <td style="width: 50%; vertical-align: top; padding-right: 10px; border-right: 1px solid #000;">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-weight: bold; font-size: 10px; text-align: center; padding-bottom: 5px;" colspan="2">CAJA ABIERTA</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #444; font-weight: bold;">Saldo Inicial:</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #444; font-weight: bold;">{{ number_format($saldoInicialReal ?? $saldoInicialCajaAbierta, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #00aa00; font-weight: bold;">Pagos (+):</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #00aa00; font-weight: bold;">+{{ number_format($totalAmortizaciones, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #dd0000; font-weight: bold;">Créditos (-):</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #dd0000; font-weight: bold;">-{{ number_format($totalCreditosEmitidos, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #006688; font-weight: bold;">Remesas (+/-):</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #006688; font-weight: bold;">{{ ($remesasNetCajaAbierta ?? 0) >= 0 ? '+' : '' }}{{ number_format($remesasNetCajaAbierta ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #00aa00; font-weight: bold;">Excedentes (+):</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #00aa00; font-weight: bold;">+{{ number_format($totalExcedentesDia ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #dd0000; font-weight: bold;">Devoluciones (-):</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #dd0000; font-weight: bold;">-{{ number_format($devolucionesDia ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-weight: bold; font-size: 9px; border-top: 1px solid #000;">TOTAL:</td>
                        <td class="monto" style="width: 40%; font-weight: bold; font-size: 9px; border-top: 1px solid #000;">{{ number_format($totalCajaAbierta, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; text-align: right; font-size: 8px; color: #0044cc; font-weight: bold; padding-top: 3px;">Cta. a Mayor:</td>
                        <td class="monto" style="width: 40%; font-size: 8px; color: #0044cc; font-weight: bold; padding-top: 3px;">{{ number_format($saldoCuentaAMayor ?? 0, 2) }}</td>
                    </tr>
                </table>
            </td>
            {{-- COLUMNA DERECHA: CAJA CHICA --}}
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-weight: bold; font-size: 10px; text-align: center; padding-bottom: 5px;" colspan="2">CAJA CHICA</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: right; font-size: 8px; color: #444; font-weight: bold;">Saldo Inicial:</td>
                        <td class="monto" style="width: 45%; font-size: 8px; color: #444; font-weight: bold;">{{ number_format($saldoInicialCajaChica ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: right; font-size: 8px; color: #dd0000; font-weight: bold;">Gastos (-):</td>
                        <td class="monto" style="width: 45%; font-size: 8px; color: #dd0000; font-weight: bold;">-{{ number_format(($totalGastos ?? 0) + ($totalCompras ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: right; font-size: 8px; color: #006688; font-weight: bold;">Remesas (+/-):</td>
                        <td class="monto" style="width: 45%; font-size: 8px; color: #006688; font-weight: bold;">{{ ($remesasNetCajaChica ?? 0) >= 0 ? '+' : '' }}{{ number_format($remesasNetCajaChica ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="width: 55%; text-align: right; font-weight: bold; font-size: 9px; border-top: 1px solid #000;">TOTAL:</td>
                        <td class="monto" style="width: 45%; font-weight: bold; font-size: 9px; border-top: 1px solid #000;">{{ number_format($totalCajaChica ?? 0, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

</body>

</html>