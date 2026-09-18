<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Préstamo Bancario #{{ $prestamo->PrestamoBancarioID }}</title>
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

        .datos-info {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-bottom: 10px;
        }

        .datos-info td {
            border: none;
            padding: 2px 4px;
            vertical-align: top;
        }

        .datos-info .label {
            color: #666;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .datos-info .value {
            font-weight: bold;
            font-size: 9px;
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

        .datos-table .center {
            text-align: center;
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
                    JALUD SAC
                </td>
                <td class="header-right" style="width: 50%;">
                    Pagina : 001<br>
                    Emision: {{ now()->format('d/m/Y') }}<br>
                    Hora&nbsp;&nbsp;&nbsp;: {{ now()->format('H:i:s') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="titulo">
        DETALLE DE PRESTAMO #{{ $prestamo->PrestamoBancarioID }}
    </div>
    <div class="titulo-separador">
        ----------------------------------------
    </div>

    {{-- DATOS DEL PRESTAMO --}}
    <div class="seccion-titulo">&nbsp;DATOS DEL PRESTAMO</div>
    <div class="seccion-subrayado">&nbsp;===================</div>

    <table class="datos-info">
        <tr>
            <td style="width: 25%;">
                <span class="label">PRESTAMISTA</span><br>
                <span class="value">{{ $prestamo->NombreBanco }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">CLIENTE / DEUDOR</span><br>
                <span class="value">{{ $prestamo->Cliente }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">CUENTA / REFERENCIA</span><br>
                <span class="value">{{ $prestamo->CuentaPrestamo ?? '-' }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">ESTADO</span><br>
                <span class="value">{{ match($prestamo->Estado) {
                    'VIGENTE' => 'VIGENTE',
                    'CANCELADO' => 'CANCELADO',
                    'CANCELADO_ANTICIPADO' => 'CANCELADO ANTIC.',
                    default => $prestamo->Estado,
                } }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">MONTO DEL PRESTAMO</span><br>
                <span class="value">S/ {{ number_format((float) $prestamo->MontoPrestamo, 2) }}</span>
            </td>
            <td>
                <span class="label">TEA</span><br>
                <span class="value">{{ number_format((float) $prestamo->TEA, 4) }}%</span>
            </td>
            <td>
                <span class="label">DESEMBOLSO</span><br>
                <span class="value">{{ $prestamo->FechaDesembolso?->format('d/m/Y') }}</span>
            </td>
            <td>
                <span class="label">VENCIMIENTO</span><br>
                <span class="value">{{ $prestamo->FechaVencimiento?->format('d/m/Y') }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">CUOTAS</span><br>
                <span class="value">{{ $prestamo->NumeroCuotas }}</span>
            </td>
            <td>
                <span class="label">DIA DE PAGO</span><br>
                <span class="value">{{ $prestamo->DiaPago }}</span>
            </td>
            <td>
                <span class="label">PAGO MENSUAL</span><br>
                <span class="value">S/ {{ number_format((float) $prestamo->PagoMensual, 2) }}</span>
            </td>
            <td>
                <span class="label">ORIGEN DE PAGO</span><br>
                <span class="value">{{ $prestamo->FuentePago }}</span>
            </td>
        </tr>
        @if($prestamo->Observaciones)
        <tr>
            <td colspan="4">
                <span class="label">OBSERVACIONES</span><br>
                <span class="value" style="font-size: 8px; font-weight: normal;">{{ $prestamo->Observaciones }}</span>
            </td>
        </tr>
        @endif
    </table>

    {{-- CRONOGRAMA DE PAGOS --}}
    <div class="seccion-separador"></div>
    <div class="seccion-titulo">&nbsp;CRONOGRAMA DE PAGOS</div>
    <div class="seccion-subrayado">&nbsp;====================</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">N°</th>
                <th style="width: 12%; text-align: center;">VENCIMIENTO</th>
                <th style="width: 13%; text-align: right;">CAPITAL</th>
                <th style="width: 12%; text-align: right;">INTERES</th>
                <th style="width: 12%; text-align: right;">COMISION</th>
                <th style="width: 11%; text-align: right;">SEGUROS</th>
                <th style="width: 13%; text-align: right;">CUOTA</th>
                <th style="width: 13%; text-align: right;">SALDO</th>
                <th style="width: 9%; text-align: center;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCapital = 0;
                $totalInteres = 0;
                $totalComision = 0;
                $totalSeguros = 0;
                $totalCuota = 0;
            @endphp

            @foreach($prestamo->cuotas as $cuota)
                @php
                    $totalCapital += (float) $cuota->Capital;
                    $totalInteres += (float) $cuota->Interes;
                    $totalComision += (float) $cuota->Comision;
                    $totalSeguros += (float) $cuota->Seguros;
                    $totalCuota += (float) $cuota->MontoCuota;

                    $estadoCuota = match($cuota->Estado) {
                        'CANCELADA' => 'PAGADA',
                        'PENDIENTE' => 'PENDIENTE',
                        'ANULADA_ANTICIPADA' => 'ANULADA',
                        default => $cuota->Estado,
                    };
                @endphp
                <tr>
                    <td class="center">{{ $cuota->Numero }}</td>
                    <td class="center">{{ $cuota->FechaVencimiento?->format('d/m/Y') }}</td>
                    <td class="monto">{{ number_format((float) $cuota->Capital, 2) }}</td>
                    <td class="monto">{{ number_format((float) $cuota->Interes, 2) }}</td>
                    <td class="monto">{{ number_format((float) $cuota->Comision, 2) }}</td>
                    <td class="monto">{{ number_format((float) $cuota->Seguros, 2) }}</td>
                    <td class="monto">{{ number_format((float) $cuota->MontoCuota, 2) }}</td>
                    <td class="monto">{{ number_format((float) $cuota->SaldoDeuda, 2) }}</td>
                    <td class="center">{{ $estadoCuota }}</td>
                </tr>
            @endforeach

            @if($prestamo->cuotas->count() > 0)
                <tr class="total-row">
                    <td></td>
                    <td class="center">TOTALES:</td>
                    <td class="monto">{{ number_format($totalCapital, 2) }}</td>
                    <td class="monto">{{ number_format($totalInteres, 2) }}</td>
                    <td class="monto">{{ number_format($totalComision, 2) }}</td>
                    <td class="monto">{{ number_format($totalSeguros, 2) }}</td>
                    <td class="monto">{{ number_format($totalCuota, 2) }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <br>

    <hr class="linea-separadora-doble">
    <hr class="linea-separadora-doble">

</body>

</html>
