<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Libreta de Pagos - {{ $cliente->NombresApellidos }}</title>
    <style>
        @page {
            size: 300mm 140mm;
            margin: 0 0 5mm 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: 300mm;
            height: 135mm;
            font-family: Arial, sans-serif;
            position: relative;
            background-color: #f0f0f0;
            /* Color ligero para ver el papel en pantalla */
        }

        /* Contenedor que simula el papel en pantalla */
        .papel {
            width: 300mm;
            height: 135mm;
            background: white;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        /* Botón de imprimir (no se imprime) */
        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        .btn-imprimir {
            padding: 10px 20px;
            background: #008542;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        /* --- ESTILOS DE TEXTO --- */
        .texto-flotante {
            position: absolute;
            top: 10mm;
            left: 5mm;
            right: 5mm;
            z-index: 10;
            pointer-events: none;
        }

        .titulo {
            color: #008542;
            font-size: 13pt;
            font-weight: bold;
            margin-left: 20mm;
        }

        .cliente {
            color: #008542;
            font-size: 8pt;
            font-weight: bold;
            margin: 2mm 0 2mm 20mm;
        }

        .etiqueta-verde {
            color: #008542;
            font-weight: bold;
            font-size: 8pt;
        }

        .info-fechas {
            color: #008542;
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 2mm;
        }

        .zona-box {
            background: yellow;
            color: red;
            padding: 2px 8px;
            font-weight: bold;
            display: inline-block;
            font-size: 9pt;
        }

        .resumen-prestamo {
            position: absolute;
            top: 14mm;
            left: 63mm;
            width: 40mm;
        }

        .resumen-prestamo td {
            font-size: 7.5pt;
            font-weight: bold;
            padding: 0px 0;
            line-height: 1;
        }

        /* AJUSTE SOLICITADO: Removido */
        .bancos {
            display: none;
        }

        /* --- CONTENEDOR DE LA REJILLA UNIFICADA --- */
        .contenedor-rejilla {
            position: absolute;
            top: 10mm;
            left: 5mm;
            width: 290mm;
        }

        .columna-tabla {
            width: 96.66mm;
            float: left;
        }

        .col-1,
        .col-2 {
            margin-right: -1.5px;
        }

        /* Alineación con la imagen de referencia */
        .col-1 {
            margin-top: 32mm;
        }

        .col-2 {
            margin-top: -0.4mm;
        }

        .col-3 {
            margin-top: -0.4mm;
        }

        table.tabla-datos {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #008542;
        }

        table.tabla-datos th {
            border: 1.5px solid #008542;
            color: #008542;
            font-size: 7pt;
            padding: 2px 3px;
            background: white;
            height: 19px;
            line-height: 1.2;
            vertical-align: middle;
        }

        table.tabla-datos tr {
            height: 19px;
        }

        table.tabla-datos td {
            border: 1.5px solid #008542;
            height: 19px;
            text-align: center;
            font-size: 7.2pt;
            font-weight: normal;
            padding: 2px 3px;
            line-height: 1.2;
            vertical-align: middle;
        }

        .marcado-rojo {
            color: red !important;
        }

        @media print {
            body {
                background: none;
            }

            .papel {
                box-shadow: none;
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="papel">
        <div class="texto-flotante">
            <div class="titulo">CONTROL DE PAGOS</div>
            <div class="cliente">{{ strtoupper($cliente->NombresApellidos) }}</div>
            <div class="info-fechas">
                <span class="etiqueta-verde">FE:</span>
                {{ \Carbon\Carbon::parse($credito->FechaGeneracion)->format('d/m/Y') }}<br>
                <span class="etiqueta-verde">FV:</span>
                {{ $cuotas->last() ? \Carbon\Carbon::parse($cuotas->last()->FechaVencimiento)->format('d/m/Y') : '--' }}
            </div>
            <div class="zona-box">{{ $zona }}</div>

            <div class="resumen-prestamo">
                <table>
                    <tr>
                        <td class="etiqueta-verde">PRINCIPAL</td>
                    </tr>
                    <tr>
                        <td class="etiqueta-verde">MONTO</td>
                        <td style="text-align:right">
                            {{ number_format(($proposicion->MontoTotal + $proposicion->MontoInteres), 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="etiqueta-verde">CUOTA</td>
                        <td style="text-align:right">{{ number_format($proposicion->MontoCuota, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="etiqueta-verde">N° DE CUOTAS</td>
                        <td style="text-align:right">{{ $proposicion->NumeroCuotas }}</td>
                    </tr>
                    <tr>
                        <td class="etiqueta-verde">PLAZO</td>
                        <td style="text-align:right">{{ $proposicion->Plazo }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="contenedor-rejilla">
            @php 
                $saldoFicticio = ($proposicion->MontoTotal + $proposicion->MontoInteres);
                $filasConfig = [13, 18, 18];
                $offsetGeneral = 0;
            @endphp
 @foreach ($filasConfig as $index => $maxFilas)
                    <div class="columna-tabla col-{{ $index + 1 }}">
                    <table class="tabla-datos">
                        <thead>
                            <tr>

                         <th style="width: 12%">FECHA</th>
                                <th style="width: 25%">EFECTIVO</th>
                                <th style="width: 40%">YAPE - TRANSFERENCIA</th>
                                <th style="width: 23%">SALDO</th>
    </tr>
                        </thead>
                    <tbody>
                        @php 
                            $items = $cuotas->slice($offsetGeneral, $maxFilas);
                            $contador = 0;
                            $offsetGeneral += $maxFilas;
                        @endphp

                        @foreach($items as $cuota)
                            @php 
                                $pago = 0;
                                $esPagoInicialFila = $cuota->NumeroCuota == 0;
                                $fechaMostrar = \Carbon\Carbon::parse($cuota->FechaVencimiento);

                                // Buscar si hay pagos para esta cuota
                                if (isset($credito->pagos)) {
                                    foreach ($credito->pagos as $p) {
                                        if ($p->CuotaID == $cuota->CuotaID) {
                                            $pago += $p->MontoPagado;
                                        }
                                    }
                                }

                                $saldoFicticio -= $pago;

                                $esDomingo = $cuota->Estado === 'DOMINGO';
                                $esFeriado = $cuota->Estado === 'FERIADO';
                                $debeResaltar = $esDomingo || $esFeriado;

                                $contador++;
                            @endphp
                            <tr class="{{ $debeResaltar ? 'marcado-rojo' : '' }}">
                                <td>
                                    @if($esPagoInicialFila)
                                        {{ $fechaMostrar->format('d/m/Y') }}<br>
                                        <span style="font-size: 5pt; color: #666;">PAGO INICIAL</span>
                                    @else
                                        {{ $fechaMostrar->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td>{{ $pago > 0 ? number_format($pago, 1) : '' }}</td>
                                <td></td>
                                <td>{{ $pago > 0 ? number_format(max(0, $saldoFicticio), 2) : '' }}</td>
                            </tr>
                        @endforeach

                        @for ($r = $contador; $r < $maxFilas; $r++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                        </table>
                    </div>
@endforeach
        </div>
    </div>

</body>
</html>