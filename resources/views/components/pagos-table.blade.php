@php
    $record = $this->record ?? null;
    $pagos = [];
    
    if ($record) {
        $pagos = $record->cuotas()
            ->with('pagos')
            ->get()
            ->flatMap(fn($cuota) => $cuota->pagos)
            ->sortBy(fn($pago) => $pago->cuota?->NumeroCuota)
            ->values();
    }
@endphp

<div class="space-y-4">
    @if($pagos->isEmpty())
        <div class="text-center text-gray-500 py-8">
            <p>No hay pagos registrados para este crédito.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left">Cuota #</th>
                        <th class="px-4 py-2 text-left">Monto Pagado</th>
                        <th class="px-4 py-2 text-left">Fecha de Pago</th>
                        <th class="px-4 py-2 text-left">Es Mora</th>
                        <th class="px-4 py-2 text-left">Usuario Registro</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pagos as $pago)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">{{ $pago->cuota?->NumeroCuota ?? '-' }}</td>
                            <td class="px-4 py-2">S/ {{ number_format($pago->MontoPagado, 2) }}</td>
                            <td class="px-4 py-2">{{ $pago->FechaPago ? \Carbon\Carbon::parse($pago->FechaPago)->format('d/m/Y H:i') : '-' }}</td>
                            <td class="px-4 py-2">
                                @if($pago->EsMora)
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded">Sí</span>
                                @else
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $pago->UsuarioRegistro ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
