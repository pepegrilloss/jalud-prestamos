<table class="data-table">
    <thead>
        <tr>
            <th style="width: 25%">FECHA</th>
            <th style="width: 20%">EFECTIVO</th>
            <th style="width: 30%">YAPE - TRANSF</th>
            <th style="width: 25%">SALDO</th>
        </tr>
    </thead>
    <tbody>
        @php $colCuotas = $cuotas->slice($colIndex * $rowsPerCol, $rowsPerCol); @endphp
        @foreach($colCuotas as $cuota)
            @php 
                $pago = $pagosData[$cuota->CuotaID] ?? 0;
                $esDomi = (\Carbon\Carbon::parse($cuota->FechaVencimiento)->dayOfWeek == 0);
                // Cálculo manual del saldo para esta vista específica
                $posicionActual = $cuotas->search($cuota);
                $saldoFila = ($proposicion->MontoTotal + $proposicion->MontoInteres) - $cuotas->take($posicionActual + 1)->sum(fn($c) => $pagosData[$c->CuotaID] ?? 0);
            @endphp
            <tr class="{{ $esDomi ? 'text-red' : '' }}">
                <td>{{ \Carbon\Carbon::parse($cuota->FechaVencimiento)->format('d/m/Y') }}</td>
                <td>{{ $pago > 0 ? number_format($pago, 2) : '' }}</td>
                <td></td>
                <td>{{ number_format($saldoFila, 2) }}</td>
            </tr>
        @endforeach
        @for ($f = count($colCuotas); $f < $rowsPerCol; $f++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>