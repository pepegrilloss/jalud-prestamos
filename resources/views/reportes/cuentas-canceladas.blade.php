<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Cuentas Canceladas</title>
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
        .total {
            text-align: right;
            font-weight: bold;
            margin-top: 10px;
            font-size: 12px;
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
        <div>08/02/2026 09:41:04</div>
    </div>

    <div style="text-align: left; margin-bottom: 30px;">
        <strong>JALUD  SAC</strong>
    </div>

    <div class="title">CUENTAS CANCELADAS EN EL DIA</div>
    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th>OPERACION</th>
                <th>CLIENTE</th>
                <th>ZONA</th>
                <th>CUENTA</th>
                <th class="numero">TOTAL</th>
                <th>FECHA SALDADO</th>
                <th>VENCIMIENTO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($proposiciones as $prop)
                <tr>
                    <td>{{ str_pad($prop->ProposicionCreditoID, 11, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $prop->cliente?->NombresApellidos ?? '-' }}</td>
                    <td>{{ $prop->zona?->Nombre ?? '-' }}</td>
                    <td>{{ $prop->CodigoCredito }}</td>
                    <td class="numero">{{ number_format($prop->MontoTotalPagar, 2) }}</td>
                    <td>{{ $prop->credito?->FechaSaldamiento?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $prop->credito?->FechaVencimiento?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">No hay datos disponibles</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Este documento fue generado automaticamente por el sistema JALUD</p>
    </div>
</body>
</html>
