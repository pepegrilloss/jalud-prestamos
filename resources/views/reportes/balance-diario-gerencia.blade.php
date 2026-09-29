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
        .reconcile td { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; }
        .empty { padding: 5px; text-align: center; }
        .totals { margin-top: 7px; text-align: right; }
        .account { margin-top: 14px; }
        .account + .account { page-break-before: auto; }
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
                    <col style="width: 6%"><col style="width: 8%"><col style="width: 13%"><col style="width: 13%">
                    <col style="width: 12%"><col style="width: 22%"><col style="width: 8%"><col style="width: 8%"><col style="width: 10%">
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

            @foreach ($titulosSeccion as $clave => $titulo)
                <div class="seccion-titulo">{{ $titulo }}</div>
                <div class="seccion-subrayado">&nbsp;==============================</div>
                <table class="datos-table">
                    <colgroup>
                        <col style="width: 6%"><col style="width: 8%"><col style="width: 13%"><col style="width: 13%">
                        <col style="width: 12%"><col style="width: 22%"><col style="width: 8%"><col style="width: 8%"><col style="width: 10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="width: 6%;">N.º</th><th style="width: 8%;">FECHA</th><th style="width: 13%;">SEDE / ORIGEN</th><th style="width: 13%;">SEDE / DESTINO</th>
                            <th style="width: 12%;">CAJA / CUENTA</th><th style="width: 22%;">CONCEPTO</th><th class="number" style="width: 8%;">INGRESO</th>
                            <th class="number" style="width: 8%;">SALIDA</th><th class="number" style="width: 10%;">SALDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($estado['secciones'][$clave] as $movimiento)
                            @php
                                $entrada = $movimiento['monto'] > 0;
                                $esRemesa = $movimiento['categoria'] === 'remesa';
                                $origen = $esRemesa ? ($entrada ? $movimiento['contraparte'] : $estado['nombre']) : $estado['nombre'];
                                $destino = $esRemesa ? ($entrada ? $estado['nombre'] : $movimiento['contraparte']) : $movimiento['concepto'];
                            @endphp
                            <tr>
                                <td>{{ $movimiento['numero'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($movimiento['fecha'])->format('d/m/Y') }}</td>
                                <td>{{ $origen }}</td>
                                <td>{{ $destino }}</td>
                                <td>{{ $movimiento['cuenta'] }}</td>
                                <td>{{ $movimiento['concepto'] }}</td>
                                <td class="number">{{ $movimiento['ingreso'] ? number_format($movimiento['ingreso'], 2) : '-' }}</td>
                                <td class="number">{{ $movimiento['salida'] ? number_format($movimiento['salida'], 2) : '-' }}</td>
                                <td class="number">{{ number_format($movimiento['saldo'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty" colspan="9">Sin movimientos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endforeach

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

    <div class="footer">Generado el {{ $generadoEn->format('d/m/Y H:i') }}. Los saldos y movimientos corresponden a los registros contables disponibles para Gerencia.</div>
</body>
</html>
