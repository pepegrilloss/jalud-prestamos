@php
    $record = (isset($getRecord) && is_callable($getRecord)) ? $getRecord() : ($this->record ?? null);
    $pagosDisplay = [];
    
    if ($record) {
        $montoTotalPagar = (float) ($record->proposicion?->MontoTotalPagar ?? 0);
        
        $pagosBase = $record->pagos()
            ->where('Activo', true)
            ->orderBy('FechaPago', 'asc')
            ->get();
        
        $tempPagos = [];
        $saldoAcumulado = $montoTotalPagar;
        
        foreach ($pagosBase as $pago) {
            $tempPagos[] = [
                'pago' => $pago,
                'saldo_antes' => $saldoAcumulado,
                'saldo_despues' => max(0, $saldoAcumulado - $pago->MontoPagado),
            ];
            $saldoAcumulado -= $pago->MontoPagado;
        }
        
        $pagosDisplay = array_reverse($tempPagos);
        $totalPagos = count($pagosDisplay);
    }
@endphp

<div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900 overflow-hidden">
    @if(empty($pagosDisplay))
        <div class="px-6 py-12 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <x-heroicon-o-banknotes class="h-6 w-6 text-gray-400" />
            </div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No hay pagos registrados para este crédito.</p>
        </div>
    @else
        <div class="overflow-x-auto overflow-y-auto max-h-[500px]">
            <table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5 text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5 font-semibold text-gray-950 dark:text-white uppercase tracking-wider text-[11px]">
                        <th class="px-3 py-2 border-r dark:border-white/10">Cuota</th>
                        <th class="px-3 py-2 border-r dark:border-white/10 text-right">Monto</th>
                        <th class="px-3 py-2 border-r dark:border-white/10">Fecha y Hora</th>
                        <th class="px-3 py-2 border-r dark:border-white/10">Forma de Pago</th>
                        <th class="px-3 py-2 border-r dark:border-white/10 text-center">Mora</th>
                        <th class="px-3 py-2 border-r dark:border-white/10 text-right">Saldo</th>
                        <th class="px-3 py-2 border-r dark:border-white/10">Recibido por</th>
                        <th class="px-3 py-2">Observación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5 leading-tight">
                    @foreach($pagosDisplay as $index => $item)
                        @php 
                            $pago = $item['pago'];
                            $numFila = $totalPagos - $index;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-3 py-1.5 border-r dark:border-white/10 font-medium text-gray-900 dark:text-white text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400 font-bold text-xs">
                                    {{ str_pad($numFila, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-right font-mono text-success-600 dark:text-success-400">
                                {{ number_format($pago->MontoPagado, 2) }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $pago->FechaPago ? $pago->FechaPago->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-gray-600 dark:text-gray-400">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ match($pago->TipoPago) {
                                    'YAPE' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                                    'TRANSFERENCIA' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                } }}">
                                    {{ $pago->TipoPago ?? 'Efectivo' }}
                                </span>
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-center">
                                @if($pago->EsMora)
                                    <span class="text-danger-600 dark:text-danger-400 font-bold">Mora</span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-600">Libre</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-right font-mono text-gray-950 dark:text-gray-200 font-bold">
                                {{ number_format($item['saldo_despues'], 2) }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-gray-600 dark:text-gray-400 truncate max-w-[150px]" title="{{ $pago->UsuarioRegistro }}">
                                {{ $pago->UsuarioRegistro ?? '-' }}
                            </td>
                            <td class="px-3 py-1.5 text-gray-500 dark:text-gray-500 italic text-[12px] truncate max-w-[200px]" title="{{ $pago->Comentario }}">
                                {{ $pago->Comentario ?? 'Conforme' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
