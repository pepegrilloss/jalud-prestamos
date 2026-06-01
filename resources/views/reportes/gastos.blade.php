<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Gastos</title>
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
            margin: 30px 0 10px 0;
            letter-spacing: 2px;
        }

        .subtitle {
            text-align: center;
            font-size: 11px;
            margin-bottom: 20px;
        }

        .line {
            border-bottom: 1px solid #000;
            margin: 10px 0 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            border: none;
            padding: 6px 8px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 10px;
        }

        table th {
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding-bottom: 5px;
            text-align: center;
            font-size: 10px;
        }

        table td.numero {
            text-align: right;
        }

        table td.centro {
            text-align: center;
        }

        .detalle-row {
            border-bottom: 1px solid #eee;
        }

        .detalle-row:last-child {
            border-bottom: 1px solid #ccc;
        }

        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
            border-top: 2px solid #000;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 40px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <div>{{ $fecha_reporte }}</div>
    </div>

    <div class="title">REPORTE DE GASTOS</div>
    <div class="subtitle">
        @if($fecha)
            Fecha: {{ $fecha }}
        @else
            Todos los registros
        @endif
    </div>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th class="centro">FECHA</th>
                <th>TIPO COMP.</th>
                <th class="centro">NÚMERO</th>
                <th>PROVEEDOR</th>
                <th>MOTIVO</th>
                <th class="centro">¿GASTO?</th>
                <th>DESCRIPCIÓN</th>
                <th class="numero">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $gasto)
                @if($gasto->detalles->isNotEmpty())
                    @foreach($gasto->detalles as $index => $detalle)
                        <tr class="detalle-row">
                            @if($index === 0)
                                <td class="centro" rowspan="{{ $gasto->detalles->count() }}">
                                    {{ $gasto->FechaEmision->format('d/m/Y') }}</td>
                                <td rowspan="{{ $gasto->detalles->count() }}">{{ $gasto->tipoComprobanteGasto->Nombre }}</td>
                                <td class="centro" rowspan="{{ $gasto->detalles->count() }}">{{ $gasto->Numero }}</td>
                                 <td rowspan="{{ $gasto->detalles->count() }}">{{ $gasto->proveedor?->Nombre }}</td>
                                <td rowspan="{{ $gasto->detalles->count() }}">{{ $gasto->motivo->Nombre }}</td>
                                <td class="centro" rowspan="{{ $gasto->detalles->count() }}">{{ $gasto->EsGasto ? 'Sí' : 'No' }}</td>
                            @endif
                            <td>{{ $detalle->Descripcion }}</td>
                            <td class="numero">S/. {{ number_format($detalle->Monto, 2) }}</td>
                        </tr>
                    @endforeach
                @else
                    {{-- Datos antiguos sin detalles --}}
                    <tr class="detalle-row">
                        <td class="centro">{{ $gasto->FechaEmision->format('d/m/Y') }}</td>
                        <td>{{ $gasto->tipoComprobanteGasto->Nombre }}</td>
                        <td class="centro">{{ $gasto->Numero }}</td>
                        <td>{{ $gasto->proveedor?->Nombre }}</td>
                        <td>{{ $gasto->motivo->Nombre }}</td>
                        <td class="centro">{{ $gasto->EsGasto ? 'Sí' : 'No' }}</td>
                        <td>{{ $gasto->Descripcion ?? '-' }}</td>
                        <td class="numero">S/. {{ number_format($gasto->Total, 2) }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">No hay datos disponibles</td>
                </tr>
            @endforelse
            @if($gastos->count() > 0)
                <tr class="total-row">
                    <td colspan="7" style="text-align: right;">TOTAL:</td>
                    <td class="numero">S/. {{ number_format($total_general, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <p>Cantidad de gastos: {{ $gastos->count() }}</p>
        <p>Este documento fue generado automaticamente por el sistema JALUD</p>
    </div>
</body>

</html>