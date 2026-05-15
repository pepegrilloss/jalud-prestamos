<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Reporte de Cartera</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 40px;
            line-height: 1.4;
        }
        .header {
            text-align: right;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 30px 0 5px 0;
            letter-spacing: 2px;
        }
        .subtitle {
            text-align: center;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .line {
            border-bottom: 1px solid #000;
            margin: 10px 0 20px 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin: 25px 0 8px 0;
            padding: 4px 8px;
            background-color: #e8e8e8;
            border-bottom: 2px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table th,
        table td {
            border: none;
            padding: 4px 6px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 10px;
        }
        table th {
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding-bottom: 3px;
        }
        table tbody tr {
            border-bottom: 1px solid #ddd;
        }
        table tbody tr:last-child {
            border-bottom: 1px solid #000;
        }
        table td.numero {
            text-align: right;
        }
        table th.numero {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            font-size: 11px;
            text-align: right;
            margin-top: 5px;
            padding: 4px 6px;
            border-top: 2px solid #000;
        }
        .total-general {
            font-weight: bold;
            font-size: 13px;
            text-align: right;
            margin-top: 20px;
            padding: 8px;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 40px;
            color: #666;
        }
        .empty-msg {
            text-align: center;
            color: #999;
            padding: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Pagina 1</div>
        <div>{{ $fechaEmision->format('d/m/Y H:i:s') }}</div>
    </div>

    <div style="text-align: left; margin-bottom: 10px;">
        <strong>JALUD  SAC</strong>
    </div>
    <div style="text-align: left; margin-bottom: 5px; font-size: 11px;">
        <strong>SEDE:</strong> {{ $sedeNombre }}
    </div>

    <div class="title">REPORTE DE CARTERA</div>
    <div class="subtitle">Créditos generados del {{ $rangoFechas }}</div>
    <div class="line"></div>

    @foreach($secciones as $key => $seccion)
        @if(!empty($seccion['creditos']))
            <div class="section-title">{{ $seccion['titulo'] }} ({{ count($seccion['creditos']) }} créditos)</div>

            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Zona</th>
                        <th class="numero">Total</th>
                        <th class="numero">Pagado</th>
                        <th class="numero">Saldo</th>
                        <th>Fecha</th>
                        <th>Fecha Ven.</th>
                        <th class="numero">Días</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($seccion['creditos'] as $item)
                        <tr>
                            <td>{{ $item['tipo'] }}</td>
                            <td>{{ $item['cliente'] }}</td>
                            <td>{{ $item['zona'] }}</td>
                            <td class="numero">{{ number_format($item['total'], 2) }}</td>
                            <td class="numero">{{ number_format($item['pagado'], 2) }}</td>
                            <td class="numero">{{ number_format($item['saldo'], 2) }}</td>
                            <td>{{ $item['fecha'] }}</td>
                            <td>{{ $item['fecha_venc'] }}</td>
                            <td class="numero">{{ $item['dias'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="total-row">
                Total Saldo {{ $seccion['titulo'] }}: S/ {{ number_format($seccion['totalSaldo'], 2) }}
            </div>
        @else
            <div class="section-title">{{ $seccion['titulo'] }}</div>
            <div class="empty-msg">No hay créditos en esta categoría para el rango seleccionado.</div>
        @endif
    @endforeach

    <div class="total-general">
        TOTAL GENERAL SALDO: S/ {{ number_format($totalGeneralSaldo, 2) }}
    </div>

    {{-- RESUMEN POR TIPO DE CARTERA --}}
    <div style="page-break-before: always;"></div>

    <div style="text-align: left; margin-bottom: 10px;">
        <strong>JALUD  SAC</strong>
    </div>
    <div style="border-bottom: 1px solid #000; margin-bottom: 30px;"></div>

    <table style="width: 60%; margin: 40px auto 0 auto;">
        <thead>
            <tr>
                <th style="text-align: right; padding-right: 20px;"></th>
                <th style="text-align: right; padding-right: 20px;"></th>
                <th style="text-align: right;"></th>
            </tr>
        </thead>
        <tbody>
            @php
                $ordenResumen = ['pesada', 'morosa', 'vencida', 'no_vencida'];
                $nombresCortos = [
                    'pesada'     => 'CARTERA PESADA',
                    'morosa'     => 'CARTERA MOROSA',
                    'vencida'    => 'CARTERA VENCIDA',
                    'no_vencida' => 'CARTERA NO VENCIDA',
                ];
            @endphp
            @foreach($ordenResumen as $tipo)
                @if(isset($secciones[$tipo]))
                    @php
                        $porcentaje = $totalGeneralSaldo > 0
                            ? ($secciones[$tipo]['totalSaldo'] / $totalGeneralSaldo) * 100
                            : 0;
                    @endphp
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="text-align: right; padding: 6px 20px 6px 0; font-weight: bold;">{{ $nombresCortos[$tipo] }}</td>
                        <td style="text-align: right; padding: 6px 20px 6px 0;">{{ number_format($secciones[$tipo]['totalSaldo'], 2) }}</td>
                        <td style="text-align: right; padding: 6px 0;">{{ number_format($porcentaje, 2) }} %</td>
                    </tr>
                @endif
            @endforeach
            <tr style="border-top: 2px solid #000;">
                <td style="text-align: right; padding: 8px 20px 8px 0; font-weight: bold; font-size: 11px;">TOTAL</td>
                <td style="text-align: right; padding: 8px 20px 8px 0; font-weight: bold; font-size: 11px;">{{ number_format($totalGeneralSaldo, 2) }}</td>
                <td style="text-align: right; padding: 8px 0; font-weight: bold; font-size: 11px;">100.00 %</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Este documento fue generado automaticamente por el sistema JALUD</p>
    </div>
</body>
</html>
