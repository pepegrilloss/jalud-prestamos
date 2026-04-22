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
                <th scope="col" class="px-4 py-3 text-center"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($cliente->proposiciones()->where('Estado', 'APROBADO')->where('FueRefinanciada', 0)->has('credito')->with(['tipoCredito', 'credito'])->get() as $proposicion)
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
                    <td class="px-4 py-3 whitespace-nowrap">{{ $proposicion->credito->FechaGeneracion?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td class="px-4 py-3 text-center">
                        <button 
                            type="button"
                            x-on:click="close(); $wire.mountAction('verDetalleCredito', { credito: {{ $proposicion->credito->CreditoID }} })"
                            title="Ver Detalle"
                            class="text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400"
                        >
                            <svg class="w-5 h-5 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        No hay créditos activos para este cliente.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
