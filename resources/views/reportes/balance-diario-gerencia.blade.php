<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Balance diario de Gerencia</title>
    <style>
        @page { margin: 10mm 8mm; }
        body { font-family: 'Courier New', Courier, monospace; color: #000; font-size: 8.5px; line-height: 1.35; margin: 0; padding: 0; }
        .header { width: 100%; margin-bottom: 10px; }
        .header-table, .datos-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .header-table { border: none; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left { text-align: left; font-size: 11px; font-weight: bold; }
        .header-right { text-align: right; font-size: 8.5px; }
        .titulo { text-align: center; margin: 15px 0 5px; font-size: 11px; font-weight: bold; }
        .titulo-separador { text-align: center; margin-bottom: 15px; }
        .seccion-titulo { margin-top: 10px; margin-bottom: 2px; font-size: 11px; font-weight: bold; page-break-after: avoid; }
        .seccion-subrayado { margin-bottom: 5px; }
        .datos-table { font-size: 8px; }
        .datos-table th { border: none; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 2px; text-align: left; font-size: 8px; font-weight: bold; }
        .datos-table td { border: none; padding: 2px; vertical-align: top; font-size: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .datos-table .number { text-align: right; padding-right: 5px; }
        .opening td { font-weight: bold; padding-top: 5px; }
        .closing td { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; font-size: 9px; }
        .section-total td { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; }
        .reconcile td { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; }
        .empty { padding: 5px; text-align: center; }
        .totals { margin-top: 7px; text-align: right; }
        .account { margin-top: 14px; }
        .account + .account { page-break-before: auto; }
        .resumen-final { margin-top: 14px; page-break-inside: avoid; }
        .resumen-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .resumen-grid > tbody > tr > td { width: 50%; padding: 5px 8px; vertical-align: top; }
        .resumen-grid > tbody > tr > td:first-child { border-right: 1px solid #000; }
        .resumen-titulo { text-align: center; font-size: 11px; font-weight: bold; margin-bottom: 2px; }
        .resumen-subtitulo { text-align: center; margin-bottom: 7px; }
        .resumen-detalle { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .resumen-detalle td { border: none; padding: 2px; vertical-align: top; }
        .resumen-detalle .importe { text-align: right; white-space: nowrap; }
        .resumen-detalle .total td { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; }
        .resumen-cuenta { padding-top: 5px !important; font-weight: bold; }
        .resumen-positivo td { color: #00aa00; font-weight: bold; }
        .resumen-negativo td { color: #dd0000; font-weight: bold; }
        .resumen-remesa td { color: #006688; font-weight: bold; }
        .linea-separadora-doble { border: none; border-top: 1px solid #000; margin: 1px 0; }
        .footer { margin-top: 15px; padding-top: 5px; border-top: 1px solid #000; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left" style="width: 50%;">JALUD SAC<br>&nbsp;&nbsp;{{ $sede->Nombre }}</td>
                <td class="header-right" style="width: 50%;">Pagina : 001<br>Emision: {{ $generadoEn->format('d/m/Y') }}<br>Hora&nbsp;&nbsp;&nbsp;: {{ $generadoEn->format('H:i:s') }}</td>
            </tr>
        </table>
    </div>
    <div class="titulo">BALANCE DIARIO DE CAJA - GERENCIA - {{ $fecha->format('d/m/Y') }}</div>
    <div class="titulo-separador">----------------------------------------</div>

    @php
        $estadosCuenta = array_merge([$caja], $cuentas);
        $titulosSeccion = [
            'remesas_entrada' => 'INGRESO DE REMESAS',
            'remesas_salida' => 'SALIDA DE REMESAS',
            'gastos' => 'GASTOS',
            'compras' => 'COMPRAS',
            'otros' => 'OTROS MOVIMIENTOS',
        ];
    @endphp

    @foreach ($estadosCuenta as $indice => $estado)
        <section class="account">
            <div class="seccion-titulo">{{ $indice === 0 ? 'CAJA GERENCIA' : strtoupper($estado['nombre']) }}</div>
            <div class="seccion-subrayado">&nbsp;==============================</div>
            <table class="datos-table">
                <colgroup>
                    <col style="width: 6%"><col style="width: 9%"><col style="width: 11%"><col style="width: 11%">
                    <col style="width: 9%"><col style="width: 24%"><col style="width: 9%"><col style="width: 9%"><col style="width: 12%">
                </colgroup>
                <thead>
                    <tr>
                        <th>N.º</th><th>FECHA</th><th>SEDE / ORIGEN</th><th>SEDE / DESTINO</th>
                        <th>CAJA / CUENTA</th><th>CONCEPTO</th><th class="number">INGRESO</th>
                        <th class="number">SALIDA</th><th class="number">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="opening"><td colspan="8">SALDO INICIAL:</td><td class="number">{{ number_format($estado['saldo_inicial'], 2) }}</td></tr>
                </tbody>
            </table>

            <div class="seccion-titulo">MOVIMIENTOS EN ORDEN CRONOLÓGICO</div>
            <div class="seccion-subrayado">&nbsp;==============================</div>
            <table class="datos-table">
                <colgroup>
                    <col style="width: 6%"><col style="width: 9%"><col style="width: 11%"><col style="width: 11%">
                    <col style="width: 9%"><col style="width: 24%"><col style="width: 9%"><col style="width: 9%"><col style="width: 12%">
                </colgroup>
                <thead>
                    <tr>
                        <th>N.º</th><th>FECHA</th><th>SEDE / ORIGEN</th><th>SEDE / DESTINO</th>
                        <th>CAJA / CUENTA</th><th>CONCEPTO</th><th class="number">INGRESO</th>
                        <th class="number">SALIDA</th><th class="number">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($estado['movimientos'] as $movimiento)
                        @php
                            $entrada = $movimiento['monto'] > 0;
                            $esRemesa = $movimiento['categoria'] === 'remesa';
                            $origen = $esRemesa ? ($entrada ? $movimiento['contraparte'] : $estado['nombre']) : $estado['nombre'];
                            $destino = $esRemesa ? ($entrada ? $estado['nombre'] : $movimiento['contraparte']) : $movimiento['concepto'];
                            $etiquetasMovimiento = [
                                'remesa' => $entrada ? 'REMESA RECIBIDA' : 'REMESA ENVIADA',
                                'gastos' => 'GASTO',
                                'compras' => 'COMPRA',
                                'otros' => 'OTRO',
                            ];
                            $concepto = ($etiquetasMovimiento[$movimiento['categoria']] ?? strtoupper($movimiento['categoria'])) . ': ' . $movimiento['concepto'];
                        @endphp
                        <tr>
                            <td>{{ $movimiento['numero'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($movimiento['fecha'])->format('d/m/Y') }}</td>
                            <td>{{ $origen }}</td>
                            <td>{{ $destino }}</td>
                            <td>{{ $movimiento['cuenta'] }}</td>
                            <td>{{ $concepto }}</td>
                            <td class="number">{{ $movimiento['ingreso'] ? number_format($movimiento['ingreso'], 2) : '-' }}</td>
                            <td class="number">{{ $movimiento['salida'] ? number_format($movimiento['salida'], 2) : '-' }}</td>
                            <td class="number">{{ number_format($movimiento['saldo'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="9">Sin movimientos</td></tr>
                    @endforelse
                    @foreach ($titulosSeccion as $clave => $titulo)
                        @php
                            $movimientosSeccion = $estado['secciones'][$clave];
                            $totalIngresosSeccion = round(array_sum(array_column($movimientosSeccion, 'ingreso')), 2);
                            $totalSalidasSeccion = round(array_sum(array_column($movimientosSeccion, 'salida')), 2);
                        @endphp
                        <tr class="section-total">
                            <td colspan="6">TOTAL {{ $titulo }}</td>
                            <td class="number">{{ number_format($totalIngresosSeccion, 2) }}</td>
                            <td class="number">{{ number_format($totalSalidasSeccion, 2) }}</td>
                            <td class="number">-</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($indice === 0 && abs($estado['excedente']) >= 0.01)
                <table class="datos-table">
                    <tbody>
                        <tr class="reconcile">
                            <td colspan="6">{{ $estado['excedente'] > 0 ? 'EXCEDENTE DE CAJA' : 'FALTANTE DE CAJA' }} (diferencia de conciliación)</td>
                            <td class="number">{{ $estado['excedente'] > 0 ? number_format($estado['excedente'], 2) : '-' }}</td>
                            <td class="number">{{ $estado['excedente'] < 0 ? number_format(abs($estado['excedente']), 2) : '-' }}</td>
                            <td class="number">{{ number_format($estado['saldo_cierre'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            <table class="datos-table">
                <tbody>
                    <tr class="closing">
                        <td colspan="8">{{ $indice === 0 ? 'TOTAL EFECTIVO' : 'SALDO FINAL ' . strtoupper($estado['nombre']) }}</td>
                        <td class="number">{{ number_format($estado['saldo_cierre'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="totals">Ingresos: S/ {{ number_format($estado['total_ingresos'], 2) }} &nbsp; | &nbsp; Salidas: S/ {{ number_format($estado['total_salidas'], 2) }}</div>
        </section>
    @endforeach

    @php
        $sumarImporte = static fn (array $movimientos, string $campo): float => round(array_sum(array_column($movimientos, $campo)), 2);
        $remesasRecibidas = $sumarImporte($caja['secciones']['remesas_entrada'], 'ingreso');
        $remesasEnviadas = $sumarImporte($caja['secciones']['remesas_salida'], 'salida');
        $gastosGerencia = $sumarImporte($caja['secciones']['gastos'], 'salida');
        $comprasGerencia = $sumarImporte($caja['secciones']['compras'], 'salida');
        $otrosIngresosGerencia = $sumarImporte($caja['secciones']['otros'], 'ingreso');
        $otrosEgresosGerencia = $sumarImporte($caja['secciones']['otros'], 'salida');
        $saldoInicialBancos = round(array_sum(array_column($cuentas, 'saldo_inicial')), 2);
        $ingresosBancos = round(array_sum(array_column($cuentas, 'total_ingresos')), 2);
        $salidasBancos = round(array_sum(array_column($cuentas, 'total_salidas')), 2);
        $saldoFinalBancos = round(array_sum(array_column($cuentas, 'saldo_cierre')), 2);
    @endphp

    <section class="resumen-final">
        <hr class="linea-separadora-doble">
        <hr class="linea-separadora-doble">
        <div class="resumen-titulo">RESUMEN DEL DÍA - GERENCIA</div>
        <div class="resumen-subtitulo">==============================</div>
        <table class="resumen-grid">
            <tbody>
                <tr>
                    <td>
                        <div class="resumen-titulo">CAJA GERENCIA</div>
                        <div class="resumen-subtitulo">================</div>
                        <table class="resumen-detalle">
                            <tbody>
                                <tr><td>Saldo inicial:</td><td class="importe">{{ number_format($caja['saldo_inicial'], 2) }}</td></tr>
                                <tr class="resumen-remesa"><td>Ingreso de remesas (+):</td><td class="importe">+{{ number_format($remesasRecibidas, 2) }}</td></tr>
                                <tr class="resumen-remesa"><td>Salida de remesas (-):</td><td class="importe">-{{ number_format($remesasEnviadas, 2) }}</td></tr>
                                <tr class="resumen-negativo"><td>Gastos (-):</td><td class="importe">-{{ number_format($gastosGerencia, 2) }}</td></tr>
                                <tr class="resumen-negativo"><td>Compras (-):</td><td class="importe">-{{ number_format($comprasGerencia, 2) }}</td></tr>
                                <tr class="resumen-positivo"><td>Otros ingresos (+):</td><td class="importe">+{{ number_format($otrosIngresosGerencia, 2) }}</td></tr>
                                <tr class="resumen-negativo"><td>Otros egresos (-):</td><td class="importe">-{{ number_format($otrosEgresosGerencia, 2) }}</td></tr>
                                @if (abs($caja['excedente']) >= 0.01)
                                    <tr class="{{ $caja['excedente'] > 0 ? 'resumen-positivo' : 'resumen-negativo' }}">
                                        <td>{{ $caja['excedente'] > 0 ? 'Excedente (+):' : 'Faltante (-):' }}</td>
                                        <td class="importe">{{ $caja['excedente'] > 0 ? '+' : '-' }}{{ number_format(abs($caja['excedente']), 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="total"><td>TOTAL EFECTIVO:</td><td class="importe">{{ number_format($caja['saldo_cierre'], 2) }}</td></tr>
                            </tbody>
                        </table>
                    </td>
                    <td>
                        <div class="resumen-titulo">CUENTAS BANCARIAS</div>
                        <div class="resumen-subtitulo">====================</div>
                        <table class="resumen-detalle">
                            <tbody>
                                <tr><td>Saldo inicial total:</td><td class="importe">{{ number_format($saldoInicialBancos, 2) }}</td></tr>
                                <tr class="resumen-positivo"><td>Ingresos del día (+):</td><td class="importe">+{{ number_format($ingresosBancos, 2) }}</td></tr>
                                <tr class="resumen-negativo"><td>Salidas del día (-):</td><td class="importe">-{{ number_format($salidasBancos, 2) }}</td></tr>
                                <tr class="total"><td>SALDO FINAL TOTAL:</td><td class="importe">{{ number_format($saldoFinalBancos, 2) }}</td></tr>
                                @forelse ($cuentas as $cuenta)
                                    <tr><td colspan="2" class="resumen-cuenta">{{ strtoupper($cuenta['nombre']) }}</td></tr>
                                    <tr><td>Saldo final:</td><td class="importe">{{ number_format($cuenta['saldo_cierre'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="2">Sin cuentas bancarias registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <div class="footer">Generado el {{ $generadoEn->format('d/m/Y H:i') }}. Los saldos y movimientos corresponden a los registros contables disponibles para Gerencia.</div>
</body>
</html>
