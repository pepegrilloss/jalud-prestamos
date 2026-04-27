<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte General del Día {{ $fecha->format('d/m/Y') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9.5px;
            color: #000;
            line-height: 1.35;
            width: 100%;
            box-sizing: border-box;
            padding: 15px 20px;
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
            font-size: 10px;
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
            max-width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            table-layout: fixed;
        }
        .datos-table th {
            border: none;
            padding: 2px 3px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
        }
        .datos-table td {
            border: none;
            padding: 1px 3px;
            font-size: 8.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .datos-table td.col-cliente {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .datos-table .monto {
            text-align: right;
            padding-right: 10px;
        }
        .linea-separadora {
            border: none;
            border-top: 1px solid #000;
            margin: 2px 0;
        }
        .linea-separadora-doble {
            border: none;
            border-top: 1px solid #000;
            margin: 1px 0;
        }

        /* ── Totales ── */
        .total-row {
            font-weight: bold;
            font-size: 10px;
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
    <div class="seccion-subrayado">
        &nbsp;=================
    </div>

    <hr class="linea-separadora">

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 22%;">OPERACION</th>
                <th style="width: 10%;">CREDITO</th>
                <th style="width: 50%;">CLIENTE</th>
                <th style="width: 18%; text-align: right; padding-right: 6px;">MONTO</th>
            </tr>
        </thead>
    </table>

    <hr class="linea-separadora">

    <table class="datos-table">
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td style="width: 22%;">{{ $pago->CodigoCredito ?? '' }}</td>
                    <td style="width: 10%;">{{ $pago->TipoCreditoCodigo ?? '001' }}</td>
                    <td style="width: 50%;" class="col-cliente">{{ mb_strtoupper($pago->NombresApellidos ?? '') }}</td>
                    <td class="monto" style="width: 18%;">{{ number_format($pago->MontoPagado, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 10px;">
                        Sin amortizaciones registradas para esta fecha
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Total amortizaciones --}}
    @if($pagos->count() > 0)
        <table class="datos-table">
            <tbody>
                <tr class="total-row">
                    <td style="width: 22%;"></td>
                    <td style="width: 10%;"></td>
                    <td style="width: 50%; text-align: right; font-weight: bold;">TOTAL AMORTIZACIONES:</td>
                    <td class="monto" style="width: 18%; font-weight: bold;">{{ number_format($totalAmortizaciones, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- ╔═══════════════════════════════════════════╗ --}}
    {{-- ║           CREDITOS EMITIDOS               ║ --}}
    {{-- ╚═══════════════════════════════════════════╝ --}}
    <div class="seccion-separador"></div>

    <div class="seccion-titulo">
        &nbsp;CREDITOS EMITIDOS
    </div>
    <div class="seccion-subrayado">
        &nbsp;=================
    </div>

    <hr class="linea-separadora">

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 18%;">OPERACION</th>
                <th style="width: 8%;">CREDITO</th>
                <th style="width: 36%;">CLIENTE</th>
                <th style="width: 13%; text-align: right; padding-right: 3px;">CAPITAL</th>
                <th style="width: 13%; text-align: right; padding-right: 3px;">INTERES</th>
                <th style="width: 12%; text-align: right; padding-right: 6px;">TOTAL</th>
            </tr>
        </thead>
    </table>

    <hr class="linea-separadora">

    <table class="datos-table">
        <tbody>
            @forelse($creditos as $credito)
                <tr>
                    <td style="width: 18%;">{{ $credito->CodigoCredito ?? '' }}</td>
                    <td style="width: 8%;">{{ $credito->TipoCreditoCodigo ?? '001' }}</td>
                    <td style="width: 36%;" class="col-cliente">{{ mb_strtoupper($credito->NombresApellidos ?? '') }}</td>
                    <td class="monto" style="width: 13%;">{{ number_format($credito->MontoTotal, 2) }}</td>
                    <td class="monto" style="width: 13%;">{{ number_format($credito->MontoInteres, 2) }}</td>
                    <td class="monto" style="width: 12%;">{{ number_format($credito->MontoTotalPagar ?? ($credito->MontoTotal + $credito->MontoInteres), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 10px;">
                        Sin créditos emitidos para esta fecha
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Total créditos emitidos --}}
    @if($creditos->count() > 0)
        <table class="datos-table">
            <tbody>
                <tr class="total-row">
                    <td style="width: 18%;"></td>
                    <td style="width: 8%;"></td>
                    <td style="width: 36%; text-align: right; font-weight: bold;">TOTAL CREDITOS EMITIDOS:</td>
                    <td class="monto" style="width: 13%; font-weight: bold;">{{ number_format($totalCreditosEmitidos, 2) }}</td>
                    <td class="monto" style="width: 13%; font-weight: bold;">{{ number_format($creditos->sum('MontoInteres'), 2) }}</td>
                    <td class="monto" style="width: 12%; font-weight: bold;">{{ number_format($creditos->sum(function($c) { return $c->MontoTotalPagar ?? ($c->MontoTotal + $c->MontoInteres); }), 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

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
