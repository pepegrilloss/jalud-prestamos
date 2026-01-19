<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante de Pagos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        @page {
            size: 80mm 120mm;
            margin: 2mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 2mm;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 5px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        .header h1 {
            margin: 0;
            font-size: 11px;
        }
        .header p {
            margin: 1px 0;
            font-size: 8px;
        }
        .info-section {
            margin-bottom: 5px;
            font-size: 8px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 1px;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 40%;
        }
        .info-value {
            display: table-cell;
            width: 60%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 8px;
        }
        th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #000;
            padding: 2px;
        }
        .total-section {
            margin-top: 5px;
            border-top: 1px solid #000;
            padding-top: 3px;
            font-size: 8px;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 1px;
        }
        .total-label {
            display: table-cell;
            font-weight: bold;
            width: 60%;
        }
        .total-value {
            display: table-cell;
            text-align: right;
            width: 40%;
        }
        .footer {
            margin-top: 5px;
            text-align: center;
            font-size: 7px;
            color: #666;
            border-top: 1px solid #000;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>COMPROBANTE DE PAGOS</h1>
        <p>{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">OPERACION:</span>
            <span class="info-value">{{ $numero_operacion ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">EMISION:</span>
            <span class="info-value">{{ $emision ? $emision->format('d/m/Y') : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">VENCIMIENTO:</span>
            <span class="info-value">{{ $vencimiento ? $vencimiento->format('d/m/Y') : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">TIPO:</span>
            <span class="info-value">{{ $tipo_credito ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">CLIENTE:</span>
            <span class="info-value">{{ $cliente_id ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label"></span>
            <span class="info-value">{{ isset($cliente_nombre) ? strtoupper($cliente_nombre) : 'N/A' }}</span>
        </div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Mto Credito:</span>
            <span class="info-value">{{ isset($monto_credito) ? number_format($monto_credito, 2) : '0.00' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Mto + Interes:</span>
            <span class="info-value">{{ isset($monto_total) ? number_format($monto_total, 2) : '0.00' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Tasa Mora:</span>
            <span class="info-value">{{ isset($monto_mora) ? number_format($monto_mora, 2) : '0.00' }}%</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            @if($pagos && count($pagos) > 0)
                @foreach($pagos as $pago)
                    <tr>
                        <td>{{ $pago->FechaPago ? $pago->FechaPago->format('d/m/Y') : 'N/A' }}</td>
                        <td style="text-align: right;">{{ number_format($pago->MontoPagado ?? 0, 1) }}</td>
                        <td style="text-align: center;">{{ $pago->EsPagoAMayor ? 'A' : 'P' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="3" style="text-align: center;">Sin pagos registrados</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Total Pagos:</span>
            <span class="total-value">{{ isset($total_pagos) ? number_format($total_pagos, 1) : '0.0' }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Inicial:</span>
            <span class="total-value">{{ isset($inicial) ? number_format($inicial, 1) : '0.0' }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Total Pagado:</span>
            <span class="total-value">{{ isset($total_pagado) ? number_format($total_pagado, 1) : '0.0' }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Mora Pagada:</span>
            <span class="total-value">{{ isset($mora_pagada) ? number_format($mora_pagada, 1) : '0.0' }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Total Deuda:</span>
            <span class="total-value">{{ isset($total_deuda) ? number_format($total_deuda, 2) : '0.00' }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Documento generado automaticamente</p>
    </div>
</body>
</html>
