<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
        <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-4 py-3">Código Crédito</th>
                <th scope="col" class="px-4 py-3">Cliente</th>
                <th scope="col" class="px-4 py-3">Tipo de Crédito</th>
                <th scope="col" class="px-4 py-3">Zona</th>
                <th scope="col" class="px-4 py-3 text-right">Monto</th>
                <th scope="col" class="px-4 py-3 text-right">Tasa (%)</th>
                <th scope="col" class="px-4 py-3 text-right">Interés</th>
                <th scope="col" class="px-4 py-3 text-right">Monto + Interés</th>
                <th scope="col" class="px-4 py-3 text-right">Saldo Pendiente</th>
                <th scope="col" class="px-4 py-3">Fecha Generación</th>
            </tr>
        </thead>
        <tbody>
            @php
                $proposicion = $credito->proposicion;
                $cliente = $proposicion->cliente;
            @endphp
            <tr class="border-b dark:border-gray-700">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {{ $proposicion->CodigoCredito }}
                </td>
                <td class="px-4 py-3">{{ $cliente->NombresApellidos }}</td>
                <td class="px-4 py-3">{{ $proposicion->tipoCredito->Descripcion ?? '-' }}</td>
                <td class="px-4 py-3">{{ $cliente->negocio->zona->Nombre ?? '-' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($proposicion->MontoTotal, 2) }} PEN</td>
                <td class="px-4 py-3 text-right">{{ number_format($proposicion->TasaInteres, 2) }} %</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($proposicion->MontoInteres, 2) }} PEN</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format($proposicion->MontoTotalPagar, 2) }} PEN</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format(\App\Models\ProposicionCredito::calcularSaldoPendiente($proposicion->ProposicionCreditoID), 2) }} PEN</td>
                <td class="px-4 py-3 whitespace-nowrap">{{ $credito->FechaGeneracion?->format('d/m/Y H:i') ?? '-' }}</td>
            </tr>
        </tbody>
    </table>
</div>

@php
    $pagos = $credito->pagos()->where('Activo', true)->orderByDesc('FechaPago')->get();
@endphp

@if($pagos->isNotEmpty())
<div x-data="{ open: false }" class="mt-4">
    <button type="button" @click="open = !open"
        class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 transition">
        <span class="flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            Historial de Pagos ({{ $pagos->count() }})
        </span>
        <svg x-bind:class="open ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-collapse class="mt-2 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2">Monto</th>
                    <th class="px-4 py-2">Fecha Pago</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Registrado por</th>
                    <th class="px-4 py-2">Comentario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $pago)
                    <tr class="border-b dark:border-gray-700">
                        <td class="px-4 py-2 text-right whitespace-nowrap font-medium">{{ number_format($pago->MontoPagado, 2) }} PEN</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $pago->FechaPago?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-2">
                            @if($pago->EsPagoAutomatico)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-info-100 text-info-700">Automático</span>
                            @elseif($pago->EsPagoAMayor)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-warning-100 text-warning-700">Pago a Mayor</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-success-100 text-success-700">Normal</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $pago->UsuarioRegistro ?? '-' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $pago->Comentario ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="mt-4 px-4 py-8 text-center text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
    Sin pagos registrados para este crédito.
</div>
@endif
