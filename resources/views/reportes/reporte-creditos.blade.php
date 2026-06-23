<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Reporte de Créditos {{ $fechaDesde->format('d/m/Y') }} - {{ $fechaHasta->format('d/m/Y') }}</title>
    <style>
        @page { margin: 10mm 8mm 10mm 8mm; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5px;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header { width: 100%; margin-bottom: 10px; }
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left { text-align: left; font-weight: bold; font-size: 11px; }
        .header-right { text-align: right; font-size: 8.5px; }
        .titulo { text-align: center; margin: 15px 0 5px 0; font-size: 12px; font-weight: bold; }
        .subtitulo { text-align: center; margin-bottom: 15px; font-size: 10px; }
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
        .datos-table .monto { text-align: right; }
        .datos-table .center { text-align: center; }
        .footer-total {
            margin-top: 8px;
            font-weight: bold;
            font-size: 9px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">JALUD PRESTAMOS<br>REPORTE DE CREDITOS</td>
                <td class="header-right">
                    Sede: {{ $sedeNombre }}<br>
                    Periodo: {{ $fechaDesde->format('d/m/Y') }} - {{ $fechaHasta->format('d/m/Y') }}<br>
                    Fecha impresion: {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="titulo">REPORTE DE CREDITOS</div>
    <div class="subtitulo">Periodo: {{ $fechaDesde->format('d/m/Y') }} - {{ $fechaHasta->format('d/m/Y') }}</div>

    <table class="datos-table">
        <thead>
            <tr>
                <th style="width:8%">DNI</th>
                <th style="width:18%">NOMBRES</th>
                <th style="width:10%" class="monto">MTO ENTREG</th>
                <th style="width:9%" class="monto">INTERES</th>
                <th style="width:10%" class="monto">MTO TOTAL</th>
                <th style="width:9%" class="center">F. ENTREGA</th>
                <th style="width:9%" class="monto">SALDO</th>
                <th style="width:9%" class="monto">COBRO MONT</th>
                <th style="width:9%" class="center">F. VENC</th>
                <th style="width:8%" class="center">TIPO</th>
                <th style="width:4%" class="center">DIAS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditos as $c)
            <tr>
                <td>{{ $c->proposicion?->cliente?->DNI ?? '-' }}</td>
                <td>{{ $c->proposicion?->cliente?->NombresApellidos ?? '-' }}</td>
                <td class="monto">{{ number_format($c->proposicion?->MontoTotal ?? 0, 2) }}</td>
                <td class="monto">{{ number_format($c->proposicion?->MontoInteres ?? 0, 2) }}</td>
                <td class="monto">{{ number_format($c->proposicion?->MontoTotalPagar ?? 0, 2) }}</td>
                <td class="center">{{ $c->FechaGeneracion?->format('d/m/Y') ?? '-' }}</td>
                <td class="monto">{{ number_format($c->proposicion?->SaldoPendiente ?? 0, 2) }}</td>
                <td class="monto">{{ number_format($c->proposicion?->MontoCuota ?? 0, 2) }}</td>
                <td class="center">{{ $c->FechaVencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td class="center">{{ $c->proposicion?->tipoCredito?->Descripcion ?? '-' }}</td>
                <td class="center">{{ $c->proposicion?->Plazo ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer-total">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:26%; border:none;">TOTAL REGISTROS: {{ $creditos->count() }}</td>
                <td style="width:10%; border:none; text-align:right;">{{ number_format($totales['montoTotal'], 2) }}</td>
                <td style="width:9%; border:none; text-align:right;">{{ number_format($totales['interes'], 2) }}</td>
                <td style="width:10%; border:none; text-align:right;">{{ number_format($totales['montoTotalPagar'], 2) }}</td>
                <td style="width:18%; border:none;"></td>
                <td style="width:9%; border:none; text-align:right;">{{ number_format($totales['saldo'], 2) }}</td>
                <td style="width:18%; border:none;"></td>
            </tr>
        </table>
    </div>
</body>
</html>
