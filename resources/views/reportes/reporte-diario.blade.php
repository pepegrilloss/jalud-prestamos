<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
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

        /* ── Header ── */
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

        /* ── Título ── */
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

        /* ── Secciones ── */
        .seccion-titulo {
            font-weight: bold;
            font-size: 11px;
            margin-top: 10px;
            margin-bottom: 2px;
        }
        .seccion-subrayado {
            margin-bottom: 5px;
        }

        /* ── Tabla de datos ── */
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

        /* ── Totales ── */
        .total-row {
            font-weight: bold;
            font-size: 9px;
        }
        .total-row td {
            padding-top: 5px;
            border-top: 1px solid #000;
        }

        /* ── Separador entre secciones ── */
        .seccion-separador {
            margin: 15px 0 8px 0;
        }

        /* ── Saldos box ── */
        .saldos-box {
            border: 1px solid #000;
            padding: 5px 8px;
            margin-bottom: 15px;
            font-size: 9px;
        }

        /* ── Page break ── */
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║            CABECERA DEL REPORTE           ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
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

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║         TÍTULO DEL REPORTE                ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="titulo">
        REPORTE GENERAL DEL DIA {{ $fecha->format('d/m/Y') }}
    </div>
    <div class="titulo-separador">
        ----------------------------------------
    </div>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     1. CAJA CHICA - GASTOS               ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-titulo">
        &nbsp;CAJA CHICA
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 3px;">
        &nbsp;==========
    </div>
    <div style="margin-left: 10px; font-weight: bold; font-size: 9px; margin-bottom: 5px;">
        Gastos (Total de gastos diarios)
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">N°</th>
                <th style="width: 20%;">PROVEEDOR</th>
                <th style="width: 20%;">MOTIVO</th>
                <th style="width: 34%;">DESCRIPCION</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $index => $gasto)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ mb_strtoupper($gasto->proveedor->Nombre ?? 'N/A') }}</td>
                    <td>{{ $gasto->motivo->Nombre ?? '' }}</td>
                    <td>{{ $gasto->detalles->pluck('Descripcion')->implode(', ') }}</td>
                    <td class="monto">{{ number_format($gasto->Total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">
                        Sin gastos registrados
                    </td>
                </tr>
            @endforelse

            @if($gastos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL GASTOS:</td>
                    <td class="monto">{{ number_format($totalGastos, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     1B. CAJA CHICA - COMPRAS             ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-titulo" style="margin-top: 15px;">
        &nbsp;CAJA CHICA (COMPRAS)
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 3px;">
        &nbsp;====================
    </div>
    <div style="margin-left: 10px; font-weight: bold; font-size: 9px; margin-bottom: 5px;">
        Compras registradas en el día
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">N°</th>
                <th style="width: 20%;">PROVEEDOR</th>
                <th style="width: 20%;">COMPROBANTE</th>
                <th style="width: 34%;">DETALLE</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($compras as $index => $compra)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ mb_strtoupper($compra->proveedor->Nombre ?? 'N/A') }}</td>
                    <td>{{ mb_strtoupper($compra->Numero ?? '') }}</td>
                    <td>{{ $compra->detalles->pluck('ProductoServicio')->implode(', ') }}</td>
                    <td class="monto">{{ number_format($compra->Total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">
                        Sin compras registradas
                    </td>
                </tr>
            @endforelse

            @if($compras->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL COMPRAS:</td>
                    <td class="monto">{{ number_format($totalCompras, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     2. INGRESO DE REMESAS                ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">
        &nbsp;INGRESO DE REMESAS
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 8px;">
        &nbsp;===================
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">ID</th>
                <th style="width: 25%;">SEDE ORIGEN</th>
                <th style="width: 15%;">CTA. ORIGEN</th>
                <th style="width: 15%;">CTA. DESTINO</th>
                <th style="width: 19%;">QUIEN ENVIA</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ingresosRemesas as $remesa)
                <tr>
                    <td>{{ $remesa->TransferenciaID }}</td>
                    <td>{{ mb_strtoupper($remesa->sedeOrigen->Nombre ?? '') }}</td>
                    <td>{{ $remesa->CuentaOrigen === 'CAJA_CHICA' ? 'Caja Chica' : 'Caja Abierta' }}</td>
                    <td>{{ $remesa->CuentaDestino === 'CAJA_CHICA' ? 'Caja Chica' : 'Caja Abierta' }}</td>
                    <td>{{ $remesa->usuarioOrigen->name ?? '' }}</td>
                    <td class="monto">{{ number_format($remesa->Monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">
                        Sin ingresos de remesas
                    </td>
                </tr>
            @endforelse

            @if($ingresosRemesas->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL INGRESO:</td>
                    <td class="monto">{{ number_format($totalIngresosRemesas, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     3. SALIDA DE REMESAS                 ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">
        &nbsp;SALIDA DE REMESAS
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 8px;">
        &nbsp;==================
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">ID</th>
                <th style="width: 25%;">SEDE DESTINO</th>
                <th style="width: 15%;">CTA. ORIGEN</th>
                <th style="width: 15%;">CTA. DESTINO</th>
                <th style="width: 19%;">QUIEN ENVIA</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($salidasRemesas as $remesa)
                <tr>
                    <td>{{ $remesa->TransferenciaID }}</td>
                    <td>{{ mb_strtoupper($remesa->sedeDestino->Nombre ?? '') }}</td>
                    <td>{{ $remesa->CuentaOrigen === 'CAJA_CHICA' ? 'Caja Chica' : 'Caja Abierta' }}</td>
                    <td>{{ $remesa->CuentaDestino === 'CAJA_CHICA' ? 'Caja Chica' : 'Caja Abierta' }}</td>
                    <td>{{ $remesa->usuarioOrigen->name ?? '' }}</td>
                    <td class="monto">{{ number_format($remesa->Monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">
                        Sin salidas de remesas
                    </td>
                </tr>
            @endforelse

            @if($salidasRemesas->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL SALIDA:</td>
                    <td class="monto">{{ number_format($totalSalidasRemesas, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     4. CAJA ABIERTA                      ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">
        &nbsp;CAJA ABIERTA
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 3px;">
        &nbsp;=============
    </div>

    {{-- 4a. Exoneración (Moras Intereses) --}}
    <div style="margin-left: 10px; font-weight: bold; font-size: 9px; margin-top: 8px; margin-bottom: 5px;">
        Exoneración (Moras e Intereses)
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">N°</th>
                <th style="width: 15%;">OPERACION</th>
                <th style="width: 35%;">CLIENTE</th>
                <th style="width: 24%;">TIPO EXONERACION</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($exoneraciones as $index => $exoneracion)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $exoneracion->credito?->proposicion?->CodigoCredito ?? '' }}</td>
                    <td>{{ mb_strtoupper($exoneracion->credito?->proposicion?->cliente?->NombresApellidos ?? '') }}</td>
                    <td>{{ $exoneracion->tipoExoneracion?->Descripcion ?? '' }}</td>
                    <td class="monto">{{ number_format($exoneracion->MontoExonerado, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">
                        Sin exoneraciones registradas
                    </td>
                </tr>
            @endforelse

            @if($exoneraciones->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL EXONERACIONES:</td>
                    <td class="monto">{{ number_format($totalExoneraciones, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- 4b. Extornos / Devoluciones --}}
    <div style="margin-left: 10px; font-weight: bold; font-size: 9px; margin-top: 10px; margin-bottom: 5px;">
        Extornos / Devoluciones
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 8%;">N°</th>
                <th style="width: 15%;">NRO OPER.</th>
                <th style="width: 35%;">CLIENTE</th>
                <th style="width: 24%;">TIPO</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($extornos as $index => $extorno)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $extorno->NroOperacion ?? '' }}</td>
                    <td>{{ mb_strtoupper($extorno->clienteOrigen?->NombresApellidos ?? 'N/A') }}</td>
                    <td>{{ $extorno->TipoExcedente ?? '' }}</td>
                    <td class="monto">{{ number_format($extorno->Monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 5px;">
                        Sin extornos / devoluciones
                    </td>
                </tr>
            @endforelse

            @if($extornos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="text-align: right; padding-right: 10px;">TOTAL EXTORNOS:</td>
                    <td class="monto">{{ number_format($totalExtornos, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- 4c. Amortizaciones --}}
    <div style="margin-left: 10px; font-weight: bold; font-size: 9px; margin-top: 10px; margin-bottom: 5px;">
        Amortizaciones (Pagos en orden que se han ingresado)
    </div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 18%;">OPERACION</th>
                <th style="width: 10%;">CREDITO</th>
                <th style="width: 54%;">CLIENTE</th>
                <th style="width: 18%; text-align: right; padding-right: 5px;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->CodigoCredito ?? '' }}</td>
                    <td>{{ $pago->TipoCreditoCodigo ?? '001' }}</td>
                    <td>{{ mb_strtoupper($pago->NombresApellidos ?? '') }}</td>
                    <td class="monto">{{ number_format($pago->MontoPagado, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 5px;">
                        Sin amortizaciones registradas
                    </td>
                </tr>
            @endforelse
            
            @if($pagos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td style="text-align: right; font-weight: bold; padding-right: 10px;">TOTAL AMORTIZACIONES:</td>
                    <td class="monto" style="font-weight: bold;">{{ number_format($totalAmortizaciones, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║     5. CREDITOS EMITIDOS                  ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>

    <div class="seccion-titulo">
        &nbsp;CREDITOS EMITIDOS
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 8px;">
        &nbsp;=================
    </div>

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
                    <td class="monto">{{ number_format($credito->MontoTotalPagar ?? ($credito->MontoTotal + $credito->MontoInteres), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 5px;">
                        Sin créditos emitidos
                    </td>
                </tr>
            @endforelse

            @if($creditos->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td style="text-align: right; font-weight: bold; padding-right: 10px;">TOTAL CREDITOS EMITIDOS:</td>
                    <td class="monto" style="font-weight: bold;">{{ number_format($totalCreditosEmitidos, 2) }}</td>
                    <td class="monto" style="font-weight: bold;">{{ number_format($creditos->sum('MontoInteres'), 2) }}</td>
                    <td class="monto" style="font-weight: bold;">{{ number_format($creditos->sum(function($c) { return $c->MontoTotalPagar ?? ($c->MontoTotal + $c->MontoInteres); }), 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║               RESUMEN GENERAL             ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>
    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

    <div style="font-weight: bold; font-size: 10px; text-align: center; margin: 10px 0 5px 0;">
        RESUMEN DEL DIA
    </div>

    <table class="datos-table" style="margin-top: 5px;">
        <tbody>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px;">
                    SALDO CAJA ABIERTA:
                </td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px;">
                    {{ number_format($saldoCajaAbierta, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 9px;">
                    SALDO CAJA CHICA:
                </td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 9px;">
                    {{ number_format($saldoCajaChica, 2) }}
                </td>
            </tr>
            <tr><td colspan="2">&nbsp;</td></tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL GASTOS (CAJA CHICA):
                </td>
                <td class="monto" style="width: 35%; font-size: 9px; color: #c00;">
                    -{{ number_format($totalGastos, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL COMPRAS (CAJA CHICA):
                </td>
                <td class="monto" style="width: 35%; font-size: 9px; color: #c00;">
                    -{{ number_format($totalCompras, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL INGRESO REMESAS:
                </td>
                <td class="monto" style="width: 35%; font-size: 9px; color: #060;">
                    +{{ number_format($totalIngresosRemesas, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL SALIDA REMESAS:
                </td>
                <td class="monto" style="width: 35%; font-size: 9px; color: #c00;">
                    -{{ number_format($totalSalidasRemesas, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL EXONERACIONES:
                </td>
                <td class="monto" style="width: 35%; font-size: 9px;">
                    {{ number_format($totalExoneraciones, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-size: 9px;">
                    TOTAL EXTORNOS / DEVOLUCIONES:
                </td>
                <td class="monto" style="width: 35%; font-size: 9px;">
                    {{ number_format($totalExtornos, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 10px;">
                    TOTAL AMORTIZACIONES DEL DIA:
                </td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 10px; color: #060;">
                    +{{ number_format($totalAmortizaciones, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 65%; text-align: right; font-weight: bold; font-size: 10px;">
                    TOTAL CREDITOS EMITIDOS DEL DIA:
                </td>
                <td class="monto" style="width: 35%; font-weight: bold; font-size: 10px; color: #c00;">
                    -{{ number_format($totalCreditosEmitidos, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

</body>
</html>
