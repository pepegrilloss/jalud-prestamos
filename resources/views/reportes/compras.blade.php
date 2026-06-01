<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Compras</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 40px; line-height: 1.4; }
        .header { text-align: right; margin-bottom: 20px; font-size: 11px; }
        .title { text-align: center; font-weight: bold; font-size: 14px; margin: 30px 0 10px 0; letter-spacing: 2px; }
        .subtitle { text-align: center; font-size: 11px; margin-bottom: 20px; }
        .line { border-bottom: 1px solid #000; margin: 10px 0 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { border: none; padding: 6px 8px; text-align: left; font-size: 10px; }
        table th { border-bottom: 1px solid #000; font-weight: bold; padding-bottom: 5px; text-align: center; }
        table td.numero { text-align: right; }
        table td.centro { text-align: center; }
        .detalle-row { border-bottom: 1px solid #eee; }
        .detalle-row:last-child { border-bottom: 1px solid #ccc; }
        .total-row { font-weight: bold; background-color: #f5f5f5; border-top: 2px solid #000; }
        .footer { text-align: center; font-size: 10px; margin-top: 40px; color: #666; }
    </style>
</head>

<body>
    <div class="header">
        <div>{{ $fecha_reporte }}</div>
    </div>

    <div class="title">REPORTE DE COMPRAS</div>
    <div class="subtitle">
        @if($fecha_desde && $fecha_hasta)
            Período: {{ $fecha_desde }} al {{ $fecha_hasta }}
        @elseif($fecha_desde)
            Desde: {{ $fecha_desde }}
        @elseif($fecha_hasta)
            Hasta: {{ $fecha_hasta }}
        @else
            Todos los registros
        @endif
    </div>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th class="centro">Fecha Emisión</th>
                <th>Tipo Comp.</th>
                <th class="centro">Serie / Correlativo</th>
                <th>RUC</th>
                <th>Proveedor</th>
                <th>Descripción</th>
                <th class="numero">Ítems</th>
                <th class="numero">Total</th>
                <th class="numero">Base IGV</th>
                <th class="numero">IGV</th>
                <th>Tipo</th>
                <th class="centro">Pago</th>
            </tr>
        </thead>
        <tbody>
            @forelse($compras as $compra)
                @php $itemsCount = $compra->detalles->count(); @endphp
                @if($itemsCount > 0)
                    @foreach($compra->detalles as $index => $detalle)
                        <tr class="detalle-row">
                            @if($index === 0)
                                <td class="centro" rowspan="{{ $itemsCount }}">{{ $compra->FechaEmision->format('d/m/Y') }}</td>
                                <td rowspan="{{ $itemsCount }}">{{ $compra->tipoComprobante->Nombre }}</td>
                                <td class="centro" rowspan="{{ $itemsCount }}">{{ $compra->Numero }}</td>
                                <td rowspan="{{ $itemsCount }}">{{ $compra->proveedor?->RUC }}</td>
                                <td rowspan="{{ $itemsCount }}">{{ $compra->proveedor?->Nombre }}</td>
                                <td rowspan="{{ $itemsCount }}">{{ $compra->detalles->pluck('ProductoServicio')->implode(', ') }}</td>
                                <td class="centro" rowspan="{{ $itemsCount }}">{{ $itemsCount }}</td>
                                <td class="numero" rowspan="{{ $itemsCount }}">S/. {{ number_format($compra->Total, 2) }}</td>
                                <td class="numero" rowspan="{{ $itemsCount }}">S/. {{ number_format($compra->SubtotalBase, 2) }}</td>
                                <td class="numero" rowspan="{{ $itemsCount }}">S/. {{ number_format($compra->MontoIGV, 2) }}</td>
                                <td rowspan="{{ $itemsCount }}">{{ $compra->TipoCompra }}</td>
                                <td class="centro" rowspan="{{ $itemsCount }}">{{ $compra->EstadoPago }}</td>
                            @endif
                        </tr>
                    @endforeach
                @else
                    <tr class="detalle-row">
                        <td class="centro">{{ $compra->FechaEmision->format('d/m/Y') }}</td>
                        <td>{{ $compra->tipoComprobante->Nombre }}</td>
                        <td class="centro">{{ $compra->Numero }}</td>
                        <td>{{ $compra->proveedor?->RUC }}</td>
                        <td>{{ $compra->proveedor?->Nombre }}</td>
                        <td>{{ $compra->ProductoServicio ?? '-' }}</td>
                        <td class="centro">1</td>
                        <td class="numero">S/. {{ number_format($compra->Total, 2) }}</td>
                        <td class="numero">S/. {{ number_format($compra->SubtotalBase, 2) }}</td>
                        <td class="numero">S/. {{ number_format($compra->MontoIGV, 2) }}</td>
                        <td>{{ $compra->TipoCompra }}</td>
                        <td class="centro">{{ $compra->EstadoPago }}</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="12" style="text-align: center;">No hay datos disponibles</td></tr>
            @endforelse
            @if($compras->count() > 0)
                <tr class="total-row">
                    <td colspan="7" style="text-align: right;">TOTAL GENERAL:</td>
                    <td class="numero">S/. {{ number_format($total_general, 2) }}</td>
                    <td class="numero">S/. {{ number_format($total_subtotal, 2) }}</td>
                    <td class="numero">S/. {{ number_format($total_igv, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <p>Cantidad de compras: {{ $compras->count() }}</p>
        <p>Documento generado automáticamente por el sistema JALUD</p>
    </div>
</body>
</html>
