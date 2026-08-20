@php
    if (!isset($proposiciones)) {
        $proposiciones = $cliente->relationLoaded('proposiciones') 
            ? $cliente->proposiciones 
            : $cliente->proposiciones()->where('Estado', 'APROBADO')->has('credito')->with(['tipoCredito', 'credito', 'zona'])->get();
    }
    $itemsJson = json_encode($proposiciones->map(fn($p) => [
        'codigo' => $p->CodigoCredito,
        'cliente' => $cliente->NombresApellidos,
        'tipo' => $p->tipoCredito?->Descripcion ?? '-',
        'zona' => $p->zona?->Nombre ?? $cliente->negocio?->zona?->Nombre ?? '-',
        'monto' => (float)$p->MontoTotal,
        'tasa' => (float)$p->TasaInteres,
        'interes' => (float)$p->MontoInteres,
        'totalPagar' => (float)$p->MontoTotalPagar,
        'saldo' => (float)($p->SaldoPendiente ?? 0),
        'fecha' => $p->credito?->FechaGeneracion?->format('d/m/Y H:i') ?? '-',
        'fechaTs' => $p->credito?->FechaGeneracion?->timestamp ?? 0,
        'creditoId' => $p->credito?->CreditoID,
        'clienteId' => $cliente->ClienteID,
    ])->values());
@endphp

<div x-data="{
    sortField: 'fecha',
    sortDir: 'desc',
    items: {{ $itemsJson }},
    get sortedItems() {
        return [...this.items].sort((a, b) => {
            let vA = this.sortField === 'fecha' ? a.fechaTs : a[this.sortField];
            let vB = this.sortField === 'fecha' ? b.fechaTs : b[this.sortField];
            if (typeof vA === 'string') {
                return this.sortDir === 'asc' ? vA.localeCompare(vB) : vB.localeCompare(vA);
            }
            return this.sortDir === 'asc' ? vA - vB : vB - vA;
        });
    },
    toggleSort(field) {
        if (this.sortField === field) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortField = field;
            this.sortDir = 'desc';
        }
    },
    sortIcon(field) {
        if (this.sortField !== field) return '';
        return this.sortDir === 'asc' ? ' &#9650;' : ' &#9660;';
    }
}" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
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
                <th scope="col" class="px-4 py-3 text-right cursor-pointer select-none hover:text-gray-900 dark:hover:text-white transition"
                    x-on:click="toggleSort('fecha')" title="Ordenar por fecha">
                    Fecha Generación<span x-html="sortIcon('fecha')" class="text-xs ml-1"></span>
                </th>
                <th scope="col" class="px-4 py-3 text-center"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(item, idx) in sortedItems" :key="item.codigo">
                <tr class="border-b dark:border-gray-700">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white" x-text="item.codigo"></td>
                    <td class="px-4 py-3" x-text="item.cliente"></td>
                    <td class="px-4 py-3" x-text="item.tipo"></td>
                    <td class="px-4 py-3" x-text="item.zona"></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-text="item.monto.toFixed(2) + ' PEN'"></td>
                    <td class="px-4 py-3 text-right" x-text="item.tasa.toFixed(2) + ' %'"></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-text="item.interes.toFixed(2) + ' PEN'"></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-text="item.totalPagar.toFixed(2) + ' PEN'"></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-text="item.saldo.toFixed(2) + ' PEN'"></td>
                    <td class="px-4 py-3 whitespace-nowrap text-right" x-text="item.fecha"></td>
                    <td class="px-4 py-3 text-center">
                        <button type="button"
                            x-on:click="close(); $wire.mountAction('verDetalleCredito', { credito: item.creditoId, cliente: item.clienteId })"
                            title="Ver Detalle"
                            class="text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400">
                            <svg class="w-5 h-5 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </button>
                    </td>
                </tr>
            </template>
            <tr x-show="items.length === 0">
                <td colspan="11" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                    No hay créditos activos para este cliente.
                </td>
            </tr>
        </tbody>
    </table>
</div>
