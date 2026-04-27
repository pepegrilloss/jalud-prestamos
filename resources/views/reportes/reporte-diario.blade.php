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
            margin: 20px 0 10px 0;
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
    {{-- ║           AMORTIZACIONES                  ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-titulo">
        &nbsp;AMORTIZACIONES
    </div>
    <div class="seccion-subrayado" style="margin-bottom: 8px;">
        &nbsp;=================
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
                    <td colspan="4" style="text-align: center; padding: 10px;">
                        Sin amortizaciones registradas para esta fecha
                    </td>
                </tr>
            @endforelse
            
            {{-- Total amortizaciones --}}
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
    {{-- ║           CREDITOS EMITIDOS               ║ --}}
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
                    <td colspan="6" style="text-align: center; padding: 10px;">
                        Sin créditos emitidos para esta fecha
                    </td>
                </tr>
            @endforelse

            {{-- Total créditos emitidos --}}
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
    {{-- ║               RESUMEN                     ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>
    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

    <table class="datos-table" style="margin-top: 10px;">
        <tbody>
            <tr>
                <td style="width: 70%; text-align: right; font-weight: bold; font-size: 10px;">
                    TOTAL AMORTIZACIONES DEL DIA:
                </td>
                <td class="monto" style="width: 30%; font-weight: bold; font-size: 10px;">
                    {{ number_format($totalAmortizaciones, 2) }}
                </td>
            </tr>
            <tr>
                <td style="width: 70%; text-align: right; font-weight: bold; font-size: 10px;">
                    TOTAL CREDITOS EMITIDOS DEL DIA:
                </td>
                <td class="monto" style="width: 30%; font-weight: bold; font-size: 10px;">
                    {{ number_format($totalCreditosEmitidos, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

</body>
</html>
