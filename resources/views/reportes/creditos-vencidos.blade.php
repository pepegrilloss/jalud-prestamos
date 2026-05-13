<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Creditos Vencidos</title>
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
        .line {
            border-bottom: 1px solid #000;
            margin: 10px 0 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th,
        table td {
            border: none;
            padding: 5px 8px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }
        table th {
            border-bottom: 1px solid #000;
            font-weight: bold;
            padding-bottom: 3px;
        }
        table tbody tr {
            border-bottom: 1px solid #ccc;
        }
        table tbody tr:last-child {
            border-bottom: 1px solid #000;
        }
        table td.numero {
            text-align: right;
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
        <div>Pagina 1</div>
        <div>{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <div style="text-align: left; margin-bottom: 30px;">
        <strong>JALUD  SAC</strong>
    </div>

    <div class="title">CREDITOS VENCIDOS</div>
    <div class="line"></div>

    <div style="margin-bottom: 15px; font-size: 11px;">
        <strong>VENCIDOS AL :</strong> {{ $fecha ?? now()->format('d/m/Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>DNI</th>
                <th>TIPO</th>
                <th>Cliente</th>
                <th class="numero">Total</th>
                <th class="numero">Pagado</th>
                <th class="numero">Saldo</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditos as $credito)
                @php
                    $pagado = \App\Models\Pago::whereHas('cuota', fn($q) => $q->where('CreditoID', $credito->CreditoID))
                        ->where('Activo', 1)
                        ->sum('MontoPagado');
                    $total = $credito->proposicion->MontoTotalPagar ?? 0;
                    $saldo = $total - $pagado;
                @endphp
                <tr>
                    <td>{{ $credito->proposicion?->cliente?->DNI ?? '-' }}</td>
                    <td>{{ $credito->proposicion?->tipoCredito?->Descripcion ?? '-' }}</td>
                    <td>{{ $credito->proposicion?->cliente?->NombresApellidos ?? '-' }}</td>
                    <td class="numero">{{ number_format($total, 1) }}</td>
                    <td class="numero">{{ number_format($pagado, 1) }}</td>
                    <td class="numero">{{ number_format($saldo, 1) }}</td>
                    <td>{{ $credito->FechaVencimiento?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No hay creditos vencidos</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Este documento fue generado automaticamente por el sistema JALUD</p>
    </div>
</body>
</html>
