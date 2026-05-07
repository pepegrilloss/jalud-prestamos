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
    {{-- 1. AMORTIZACIONES --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-titulo">&nbsp;AMORTIZACIONES</div>
    <div class="seccion-subrayado">&nbsp;===============</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 13%;">OPERACION</th>
                <th style="width: 15%;">ZONA</th>
                <th style="width: 9%;">CRED.</th>
                <th style="width: 45%;">CLIENTE</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->CodigoCredito ?? '' }}</td>
                    <td>{{ mb_strtoupper($pago->ZonaNombre ?? 'N/A') }}</td>
                    <td>{{ $pago->TipoCreditoCodigo ?? '001' }}</td>
                    <td>{{ mb_strtoupper($pago->NombresApellidos ?? '') }}</td>
                    <td class="monto">{{ number_format($pago->MontoPagado, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">Sin amortizaciones registradas</td>
                </tr>
            @endforelse

            @if($pagos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL AMORTIZACIONES:</td>
                    <td class="monto">{{ number_format($totalAmortizaciones, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ═══════════════════════════════════════ --}}
    {{-- 2. EXTORNOS Y DEVOLUCIONES --}}
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
    {{-- 3. CREDITOS EMITIDOS --}}
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
    {{-- 4. BALANCE DE CAJA --}}
    {{-- ═══════════════════════════════════════ --}}
    <div class="seccion-separador"></div>
    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

    <div class="seccion-titulo" style="text-align: center; font-size: 10px;">&nbsp;BALANCE DE CAJA</div>
    <div class="seccion-subrayado" style="text-align: center;">&nbsp;==================</div>

    <table class="datos-table" style="margin-top: 5px;">
        <tbody>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px; color: #555;">SALDO INICIAL DEL DIA (CAJA ABIERTA):</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px; color: #555;">
                    {{ number_format($saldoInicialCajaAbierta, 2) }}</td>
            </tr>
            @if(isset($totalInyeccionesDia) && $totalInyeccionesDia > 0)
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px; color: #28a745;">INYECCIONES DE CAPITAL (CAJA ABIERTA):</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px; color: #28a745;">
                    +{{ number_format($totalInyeccionesDia, 2) }}</td>
            </tr>
            @endif
            @if(isset($totalOtrasOperacionesDia) && $totalOtrasOperacionesDia != 0)
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px; color: #17a2b8;">OTRAS OPERACIONES CAJA ABIERTA (Remesas, Traslados):</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px; color: #17a2b8;">
                    {{ $totalOtrasOperacionesDia > 0 ? '+' : '' }}{{ number_format($totalOtrasOperacionesDia, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px;">SALDO CIERRE DEL DIA (CAJA ABIERTA):</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px; border-top: 1px solid #000;">
                    {{ number_format($saldoCajaAbierta, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px;">CUENTA A MAYOR:</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px; color: #0056b3;">
                    {{ number_format($saldoCuentaAMayor, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px;">SALDO CAJA CHICA:</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px;">
                    {{ number_format($saldoCajaChica, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">&nbsp;</td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 10px;">TOTAL AMORTIZACIONES DEL
                    DIA:</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 10px; color: #060;">
                    +{{ number_format($totalAmortizaciones, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 10px;">TOTAL CREDITOS EMITIDOS
                    DEL DIA:</td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 10px; color: #c00;">
                    -{{ number_format($totalCreditosEmitidos, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

</body>

</html>