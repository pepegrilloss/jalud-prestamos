<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Créditos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 15px;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 2px;
            font-weight: bold;
        }
        .fecha {
            text-align: center;
            margin-bottom: 15px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        th {
            background-color: #e8e8e8;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        td {
            vertical-align: middle;
            font-size: 11px;
        }
        .text-left {
            text-align: center;
        }
        .text-right {
            text-align: center;
        }
        .firma-cell {
            height: 50px;
        }
        .firmas-section {
            margin-top: 60px;
            width: 100%;
        }
        .firma-container {
            display: table;
            width: 100%;
            margin-top: 40px;
        }
        .firma-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .firma-linea {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto 5px auto;
        }
        .firma-cargo {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .firma-nombre {
            font-size: 11px;
        }
        .observacion-section {
            margin-top: 30px;
        }
        .observacion-titulo {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .observacion-linea {
            border-bottom: 1px solid #000;
            margin-bottom: 8px;
            height: 15px;
        }
        .col-codigo { width: 7%; }
        .col-zona { width: 10%; }
        .col-codigo-cliente { width: 5%; }
        .col-nombre { width: 15%; }
        .col-monto { width: 8%; }
        .col-tasa { width: 5%; }
        .col-monto-total { width: 8%; }
        .col-intereses { width: 8%; }
        .col-cuotas { width: 5%; }
        .col-dias { width: 5%; }
        .col-tipo { width: 12%; }
        .col-firma { width: 12%; }
    </style>
</head>
<body>
    <h1>ACTA DE CRÉDITOS</h1>
    <div class="fecha">Fecha: {{ $fecha }}</div>

    <table>
        <thead>
            <tr>
                <th class="col-codigo">CÓDIGO<br>CRÉDITO</th>
                <th class="col-zona">ZONA</th>
                <th class="col-codigo-cliente">CÓDIGO<br>CLIENTE</th>
                <th class="col-nombre">NOMBRE<br>CLIENTE</th>
                <th class="col-monto">MONTO</th>
                <th class="col-tasa">TASA</th>
                <th class="col-monto-total">MONTO<br>TOTAL</th>
                <th class="col-intereses">INTERESES</th>
                <th class="col-cuotas">CUOTAS</th>
                <th class="col-dias">DÍAS</th>
                <th class="col-tipo">TIPO DE<br>CRÉDITO</th>
                <th class="col-firma">FIRMA</th>
            </tr>
        </thead>
        <tbody>
            @forelse($proposiciones as $proposicion)
            <tr>
                <td>{{ $proposicion->CodigoCredito }}</td>
                <td>{{ $proposicion->zona->Nombre ?? 'N/A' }}</td>
                <td>{{ $proposicion->ClienteID }}</td>
                <td>{{ $proposicion->cliente->NombresApellidos ?? 'N/A' }}</td>
                <td>S/ {{ number_format($proposicion->MontoTotal, 2) }}</td>
                <td>{{ number_format($proposicion->TasaInteres, 2) }}%</td>
                <td>S/ {{ number_format(($proposicion->MontoTotal + $proposicion->MontoInteres), 2) }}</td>
                <td>S/ {{ number_format($proposicion->MontoInteres, 2) }}</td>
                <td>{{ $proposicion->NumeroCuotas }}</td>
                <td>{{ $proposicion->Plazo }}</td>
                <td>{{ $proposicion->tipoCredito->Descripcion ?? 'N/A' }}</td>
                <td class="firma-cell"></td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center">No hay créditos registrados</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="firmas-section">
        <div class="firma-container">
            <div class="firma-box">
                <div class="firma-linea"></div>
                <div class="firma-cargo">JEFE DE OFICINA</div>
            </div>
            <div class="firma-box">
                <div class="firma-linea"></div>
                <div class="firma-cargo">ASISTENTE ADMINISTRATIVO</div>
            </div>
        </div>
    </div>

    <div class="observacion-section">
        <div class="observacion-titulo">OBSERVACIÓN:</div>
        <div class="observacion-linea"></div>
        <div class="observacion-linea"></div>
        <div class="observacion-linea"></div>
    </div>
</body>
</html>