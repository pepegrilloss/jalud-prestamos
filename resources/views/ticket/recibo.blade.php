<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Crédito</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 5px;
        }

        .ticket {
            width: 80mm;
            margin: 0 auto;
            background: white;
            border: 2px solid #000;
            font-size: 10px;
            line-height: 1.2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        th {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
            font-weight: bold;
        }

        .logo {
            font-size: 16px;
            font-weight: bold;
            padding: 6px;
        }

        .fecha-header {
            font-size: 10px;
            font-weight: bold;
            padding: 4px;
            text-align: center;
        }

        .fecha-content {
            padding: 4px;
            text-align: center;
            font-size: 11px;
        }

        .label-cell {
            font-weight: bold;
            font-size: 10px;
            padding: 4px;
            width: 25%;
        }

        .value-cell {
            padding: 4px;
            font-size: 10px;
        }

        .firma-cell {
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            padding: 20px 4px;
            height: 60px;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <table>
            <!-- Fila 1: Logo y Fecha -->
            <tr>
                <td class="logo" colspan="2" rowspan="2">JALUD</td>
                <td class="fecha-header" colspan="3">FECHA</td>
            </tr>
            <tr>
                <td class="fecha-content">{{ substr($fecha, 0, 2) }}</td>
                <td class="fecha-content">{{ substr($fecha, 3, 2) }}</td>
                <td class="fecha-content">{{ substr($fecha, 6, 2) }}</td>
            </tr>

            <!-- Fila 2: Apellidos y Nombres -->
            <tr>
                <td class="label-cell" colspan="2">APELLIDOS Y NOMBRES:</td>
                <td class="value-cell" colspan="3">{{ $apellidos }}<br>{{ $nombres }}</td>
            </tr>

            <!-- Fila 3: Monto con FIRMA -->
            <tr>
                <td class="label-cell">MONTO</td>
                <td class="value-cell" colspan="2">{{ $monto }}</td>
                <td class="value-cell" colspan="2" rowspan="4" style="text-align: center; vertical-align: bottom; padding-bottom: 8px; font-weight: bold; font-size: 10px; height: 120px;">FIRMA</td>
            </tr>

            <!-- Fila 4: Plazo -->
            <tr>
                <td class="label-cell">PLAZO</td>
                <td class="value-cell" colspan="2">{{ $plazo }}</td>
            </tr>

            <!-- Fila 5: Observación -->
            <tr>
                <td class="label-cell">OBSERVACION</td>
                <td class="value-cell" colspan="2">{{ $observacion ?? '' }}</td>
            </tr>

            <!-- Fila 6: Teléfono -->
            <tr>
                <td class="label-cell">TELEFONO</td>
                <td class="value-cell" colspan="2">{{ $telefono }}</td>
            </tr>

            <!-- Fila 7: Zona y DNI -->
            <tr>
                <td class="label-cell">ZONA</td>
                <td class="value-cell" colspan="2">{{ $zona }}</td>
                <td class="label-cell" style="text-align: right;">DNI:</td>
                <td class="value-cell">{{ $dni }}</td>
            </tr>

            <!-- Fila 8: Recibí Conforme -->
            <tr>
                <td colspan="5" style="text-align: center; font-weight: bold; padding: 8px;">
                    RECIBI CONFORME
                </td>
            </tr>
        </table>
    </div>
</body>
</html>