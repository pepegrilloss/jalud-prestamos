<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Clientes Inactivos</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; font-size: 11px; padding: 40px; line-height: 1.4; }
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
    </style>
</head>
<body>
    <div class="header"><div>{{ now()->format('d/m/Y H:i:s') }}</div></div>
    <div style="text-align: left; margin-bottom: 30px;"><strong>JALUD SAC</strong></div>
    <div class="title">CLIENTES INACTIVOS</div>
    <div class="line"></div>
    <div style="margin-bottom: 15px; font-size: 10px;"><strong>FECHA DE CORTE:</strong> {{ $fecha }}</div>

    <table>
        <thead>
            <tr>
                <th>DNI</th>
                <th>Cliente</th>
                <th>Último Crédito</th>
                <th class="numero">Monto</th>
                <th class="numero">Monto + Interés</th>
                <th>Fecha Saldado</th>
                <th class="centro">Días Inactivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clientes as $cliente)
                @php
                    $diasInactivo = $cliente->fecha_saldado
                        ? \App\Services\DiasHabilesCalculator::contarDiasHabiles(
                            \Carbon\Carbon::parse($cliente->fecha_saldado)->addDay(), now()
                        )
                        : 0;
                @endphp
                <tr>
                    <td>{{ $cliente->DNI ?? '-' }}</td>
                    <td>{{ $cliente->NombresApellidos ?? '-' }}</td>
                    <td>{{ $cliente->ultimo_codigo ?? '-' }}</td>
                    <td class="numero">{{ number_format((float) ($cliente->ultimo_monto ?? 0), 2) }}</td>
                    <td class="numero">{{ number_format((float) ($cliente->ultimo_monto_total ?? 0), 2) }}</td>
                    <td>{{ $cliente->fecha_saldado ? \Carbon\Carbon::parse($cliente->fecha_saldado)->format('d/m/Y') : '-' }}</td>
                    <td class="centro">{{ $diasInactivo }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align: center;">No hay clientes inactivos</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer"><p>Documento generado automáticamente por el sistema JALUD</p></div>
</body>
</html>
