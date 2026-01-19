@php
    $pagos = $getRecord()?->cuotas()
        ->with('pagos')
        ->get()
        ->flatMap(fn($cuota) => $cuota->pagos)
        ->sortByDesc('FechaPago')
        ->values() ?? collect();
@endphp

<div class="space-y-2">
    @if($pagos->isEmpty())
        <span class="text-gray-500 text-sm">Sin pagos</span>
    @else
        <div class="space-y-1">
            @foreach($pagos->take(3) as $pago)
                <div class="text-xs border-l-2 border-blue-500 pl-2 py-1">
                    <div class="font-semibold">S/ {{ number_format($pago->MontoPagado, 2) }}</div>
                    <div class="text-gray-600">{{ $pago->FechaPago ? \Carbon\Carbon::parse($pago->FechaPago)->format('d/m/Y') : '-' }}</div>
                    @if($pago->EsPagoAMayor)
                        <span class="inline-block mt-1 px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">Pago a Mayor</span>
                    @endif
                </div>
            @endforeach
            @if($pagos->count() > 3)
                <div class="text-xs text-blue-600 font-semibold pt-1">
                    +{{ $pagos->count() - 3 }} pago(s) más
                </div>
            @endif
        </div>
        <div class="text-xs text-gray-600 pt-2 border-t">
            <strong>Total pagado:</strong> S/ {{ number_format($pagos->sum('MontoPagado'), 2) }}
        </div>
    @endif
</div>
