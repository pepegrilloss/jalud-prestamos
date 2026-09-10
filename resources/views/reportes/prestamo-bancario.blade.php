<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Préstamo {{ $prestamo->PrestamoBancarioID }}</title>
    <style>
        body { color: #182216; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        .muted { color: #667085; margin: 0 0 24px; }
        .details { display: grid; gap: 12px; grid-template-columns: repeat(4, 1fr); margin-bottom: 24px; }
        .label { color: #667085; display: block; font-size: 11px; margin-bottom: 4px; text-transform: uppercase; }
        .value { font-weight: 700; }
        table { border-collapse: collapse; font-size: 12px; width: 100%; }
        th, td { border: 1px solid #dce8d5; padding: 8px; text-align: right; }
        th { background: #f4f8ef; text-align: center; }
        td:first-child, td:nth-child(2) { text-align: center; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <h1>Detalle de préstamo</h1>
    <p class="muted">Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="details">
        <div><span class="label">Prestamista</span><span class="value">{{ $prestamo->NombreBanco }}</span></div>
        <div><span class="label">Cliente / deudor</span><span class="value">{{ $prestamo->Cliente }}</span></div>
        <div><span class="label">Cuenta / referencia</span><span class="value">{{ $prestamo->CuentaPrestamo }}</span></div>
        <div><span class="label">Estado</span><span class="value">{{ $prestamo->Estado }}</span></div>
        <div><span class="label">Monto del préstamo</span><span class="value">S/ {{ number_format((float) $prestamo->MontoPrestamo, 2) }}</span></div>
        <div><span class="label">TEA</span><span class="value">{{ number_format((float) $prestamo->TEA, 4) }}%</span></div>
        <div><span class="label">Desembolso</span><span class="value">{{ $prestamo->FechaDesembolso?->format('d/m/Y') }}</span></div>
        <div><span class="label">Vencimiento</span><span class="value">{{ $prestamo->FechaVencimiento?->format('d/m/Y') }}</span></div>
    </div>

    <h2>Cronograma de pagos</h2>
    <table>
        <thead><tr><th>N°</th><th>Vencimiento</th><th>Capital</th><th>Interés</th><th>Comisión</th><th>Seguros</th><th>Cuota</th><th>Saldo</th><th>Estado</th></tr></thead>
        <tbody>
        @foreach($prestamo->cuotas as $cuota)
            <tr>
                <td>{{ $cuota->Numero }}</td><td>{{ $cuota->FechaVencimiento?->format('d/m/Y') }}</td>
                <td>S/ {{ number_format((float) $cuota->Capital, 2) }}</td><td>S/ {{ number_format((float) $cuota->Interes, 2) }}</td>
                <td>S/ {{ number_format((float) $cuota->Comision, 2) }}</td><td>S/ {{ number_format((float) $cuota->Seguros, 2) }}</td>
                <td>S/ {{ number_format((float) $cuota->MontoCuota, 2) }}</td><td>S/ {{ number_format((float) $cuota->SaldoDeuda, 2) }}</td><td>{{ $cuota->Estado }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
