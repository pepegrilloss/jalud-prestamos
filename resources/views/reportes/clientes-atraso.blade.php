<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Clientes con Atraso</title>
    <style>
        * { margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 40px;
            line-height: 1.4;
        }
        .header { text-align: right; margin-bottom: 20px; font-size: 10px; }
        .title { text-align: center; font-weight: bold; font-size: 14px; margin: 30px 0 10px 0; letter-spacing: 2px; }
        .line { border-bottom: 1px solid #000; margin: 10px 0 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { border: none; padding: 4px 6px; text-align: left; font-size: 10px; }
        table th { border-bottom: 1px solid #000; font-weight: bold; padding-bottom: 3px; }
        table tbody tr { border-bottom: 1px solid #ccc; }
        table tbody tr:last-child { border-bottom: 1px solid #000; }
        td.numero { text-align: right; }
        td.centro { text-align: center; }
        .footer { text-align: center; font-size: 9px; margin-top: 40px; color: #666; }
        .badge-danger { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div>{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <div style="text-align: left; margin-bottom: 30px;">
        <strong>JALUD SAC</strong>
    </div>

    <div class="title">CLIENTES CON DÍAS DE ATRASO</div>
    <div class="line"></div>

    <div style="margin-bottom: 15px; font-size: 10px;">
        <strong>FECHA DE CORTE:</strong> {{ $fecha ?? now()->format('d/m/Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>DNI</th>
                <th>Cliente</th>
                <th>Zona</th>
                <th class="numero">Monto</th>
                <th class="numero">Monto + Interés</th>
                <th class="numero">Saldo</th>
                <th class="centro">Días Atraso</th>
                <th>Vencimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditos as $credito)
                @php
                    $diasAtraso = $credito->dias_atraso_calc ?? 0;
                @endphp
                <tr>
                    <td>{{ $credito->proposicion->CodigoCredito ?? '-' }}</td>
                    <td>{{ $credito->proposicion->cliente->DNI ?? '-' }}</td>
                    <td>{{ $credito->proposicion->cliente->NombresApellidos ?? '-' }}</td>
                    <td>{{ $credito->proposicion->zona->Nombre ?? '-' }}</td>
                    <td class="numero">{{ number_format($credito->proposicion->MontoTotal ?? 0, 2) }}</td>
                    <td class="numero">{{ number_format($credito->proposicion->MontoTotalPagar ?? 0, 2) }}</td>
                    <td class="numero">{{ number_format($credito->proposicion->SaldoPendiente ?? 0, 2) }}</td>
                    <td class="centro badge-danger">{{ $diasAtraso }}</td>
                    <td>{{ $credito->FechaVencimiento?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center;">No hay clientes con atraso</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generado automáticamente por el sistema JALUD</p>
    </div>
</body>
</html>
