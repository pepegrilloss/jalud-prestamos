<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Balance diario de Gerencia</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #202820; font-size: 9px; }
        .header { border-bottom: 2px solid #8fbd2c; margin-bottom: 14px; padding-bottom: 8px; }
        .title { font-size: 17px; font-weight: bold; color: #26351d; }
        .subtitle { margin-top: 4px; color: #687268; font-size: 9px; }
        .account { margin-top: 14px; }
        .account-title { background: #8fbd2c; color: #17200d; padding: 7px 8px; font-size: 12px; font-weight: bold; }
        .section-title { background: #f4e0d5; padding: 5px 7px; margin-top: 7px; font-weight: bold; color: #253024; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d8ddd4; padding: 4px 5px; vertical-align: top; }
        th { background: #f0f2ed; text-align: left; font-weight: bold; }
        .number { text-align: right; white-space: nowrap; }
        .muted { color: #768076; }
        .opening td { background: #fbf4ef; font-weight: bold; }
        .closing td { background: #8fbd2c; color: #14200a; font-weight: bold; font-size: 10px; }
        .reconcile td { background: #f3f3f3; font-weight: bold; }
        .empty { color: #7a8179; text-align: center; padding: 7px; }
        .totals { margin-top: 7px; color: #53604e; text-align: right; }
        .footer { margin-top: 15px; padding-top: 5px; border-top: 1px solid #d8ddd4; color: #778075; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">BALANCE DIARIO DE CAJA - GERENCIA</div>
        <div class="subtitle">Sede: {{ $sede->Nombre }} &nbsp; | &nbsp; Fecha: {{ $fecha->format('d/m/Y') }}</div>
    </div>

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
            <div class="account-title">{{ $indice === 0 ? 'CAJA GERENCIA' : strtoupper($estado['nombre']) }}</div>
            <table>
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
                    <tr class="opening">
                        <td colspan="8">SALDO INICIAL</td>
                        <td class="number">{{ number_format($estado['saldo_inicial'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            @foreach ($titulosSeccion as $clave => $titulo)
                <div class="section-title">{{ $titulo }}</div>
                <table>
                    <colgroup>
                        <col style="width: 6%"><col style="width: 8%"><col style="width: 13%"><col style="width: 13%">
                        <col style="width: 12%"><col style="width: 22%"><col style="width: 8%"><col style="width: 8%"><col style="width: 10%">
                    </colgroup>
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
                <table>
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

            <table>
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
