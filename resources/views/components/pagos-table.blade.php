@php
    $record = (isset($getRecord) && is_callable($getRecord)) ? $getRecord() : ($this->record ?? null);
    $pagosDisplay = [];
    
    if ($record) {
        $montoTotalPagar = (float) ($record->proposicion?->MontoTotalPagar ?? 0);
        
        $pagosBase = $record->relationLoaded('pagos')
            ? $record->pagos
            : $record->pagos()
                ->with(['solicitudResolucion.excedente'])
                ->where('Activo', true)
                ->orderBy('FechaPago', 'asc')
                ->get();
        
        $tempPagos = [];
        $saldoAcumulado = $montoTotalPagar;

        // Agrupar por fecha exacta, pero separar pagos a mayor / a mayor por mora
        $pagosAgrupados = [];
        foreach ($pagosBase as $pago) {
            $fechaStr = $pago->FechaPago ? $pago->FechaPago->format('Y-m-d') : '0000-00-00';
            
            // Los pagos a mayor y a mayor por mora van como filas independientes
            if ($pago->EsPagoAMayor || $pago->EsPagoAMayorPorMora) {
                $key = $fechaStr . '_amayor_' . $pago->PagoID;
                $pagosAgrupados[$key] = [$pago];
            // Los pagos trasladados también van como filas independientes
            } elseif ($pago->EstadoTraslado === 'TRASLADADO') {
                $key = $fechaStr . '_trasladado_' . $pago->PagoID;
                $pagosAgrupados[$key] = [$pago];
            } else {
                $key = $fechaStr . '_normal';
                if (!isset($pagosAgrupados[$key])) {
                    $pagosAgrupados[$key] = [];
                }
                $pagosAgrupados[$key][] = $pago;
            }
        }

        foreach ($pagosAgrupados as $grupoKey => $pagosDelDia) {
            $montoTotalDia = 0;
            $esTrasladadoTotal = true;
            $esMora = false;
            $fechaRepresentativa = null;
            $tiposPago = [];
            $usuarios = [];
            $observaciones = [];
            $montoVisualSum = 0;

            $esPagoAMayor = false;
            $esPagoAMayorPorMora = false;
            $esPagoAutomatico = false;

            foreach ($pagosDelDia as $p) {
                $esTrasladado = $p->EstadoTraslado === 'TRASLADADO';
                $montoReal = $esTrasladado ? -$p->MontoPagado : (($p->EsMora) ? 0 : $p->MontoPagado);
                $montoTotalDia += $montoReal;
                $montoVisualSum += $p->MontoPagado;

                if (!$esTrasladado) $esTrasladadoTotal = false;
                if ($p->EsMora) $esMora = true;
                if ($p->EsPagoAMayor) $esPagoAMayor = true;
                if ($p->EsPagoAMayorPorMora) $esPagoAMayorPorMora = true;
                if ($p->EsPagoAutomatico) $esPagoAutomatico = true;
                
                $tiposPago[] = $p->TipoPago ?? 'Efectivo';
                if ($p->UsuarioRegistro) $usuarios[] = $p->UsuarioRegistro;
                if ($p->FechaPago) $fechaRepresentativa = $p->FechaPago;

                $obs = $p->Comentario ?? 'Conforme';
                if ($p->solicitudResolucion && !str_contains($obs, 'Fecha')) {
                    $fechaExc = $p->solicitudResolucion->excedente?->Fecha;
                    if ($fechaExc) {
                        $obs .= "\nFecha excedente: " . \Carbon\Carbon::parse($fechaExc)->format('d/m/Y');
                    }
                }

                if (count($pagosDelDia) > 1) {
                    if ($p->EsPagoAMayorPorMora) {
                        $prefix = "Pago A Mayor Por Mora";
                    } elseif ($p->EsPagoAMayor) {
                        $prefix = "Pago A Mayor";
                    } else {
                        $prefix = "Pago Normal";
                    }
                    $observaciones[] = "• {$prefix} (S/ " . number_format($p->MontoPagado, 2) . "):\n  " . str_replace("\n", "\n  ", $obs);
                } else {
                    $observaciones[] = $obs;
                }
            }

            // Crear un objeto mock para que la vista lo lea igual que un modelo Pago
            $pagoMock = new \App\Models\Pago();
            $pagoMock->MontoPagado = $montoVisualSum;
            $pagoMock->FechaPago = $fechaRepresentativa;
            $pagoMock->TipoPago = collect($tiposPago)->unique()->implode(' / ');
            $pagoMock->EsMora = $esMora;
            $pagoMock->EsPagoAMayor = $esPagoAMayor;
            $pagoMock->EsPagoAMayorPorMora = $esPagoAMayorPorMora;
            $pagoMock->EsPagoAutomatico = $esPagoAutomatico;
            $pagoMock->UsuarioRegistro = collect($usuarios)->unique()->implode(', ');
            $pagoMock->Comentario = implode("\n\n", $observaciones);
            $pagoMock->solicitudResolucion = null;

            $tempPagos[] = [
                'pago' => $pagoMock,
                'saldo_antes' => $saldoAcumulado,
                'saldo_despues' => max(0, $saldoAcumulado - $montoTotalDia),
                'es_trasladado' => $esTrasladadoTotal,
            ];
            $saldoAcumulado -= $montoTotalDia;
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
                            $esTrasladado = $item['es_trasladado'];
                            $rowClass = $esTrasladado ? 'bg-danger-50 dark:bg-danger-900/20 hover:bg-danger-100 dark:hover:bg-danger-900/30 transition-colors' : 'hover:bg-gray-50 dark:hover:bg-white/5 transition-colors';
                            $textClass = $esTrasladado ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-400';
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="px-3 py-1.5 border-r dark:border-white/10 font-medium text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $esTrasladado ? 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-400' : 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400' }} font-bold text-xs">
                                    {{ str_pad($numFila, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-right font-mono {{ $esTrasladado ? 'text-danger-600 dark:text-danger-400 font-bold' : 'text-success-600 dark:text-success-400' }}">
                                {{ $esTrasladado ? '-' : '' }}{{ number_format($pago->MontoPagado, 2) }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 {{ $textClass }} whitespace-nowrap">
                                {{ $pago->FechaPago ? $pago->FechaPago->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 {{ $textClass }}">
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
                                    <span class="{{ $esTrasladado ? 'text-danger-500' : 'text-gray-400 dark:text-gray-600' }}">Libre</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 text-right font-mono font-bold {{ $esTrasladado ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-gray-200' }}">
                                {{ number_format($item['saldo_despues'], 2) }}
                            </td>
                            <td class="px-3 py-1.5 border-r dark:border-white/10 truncate max-w-[150px] {{ $textClass }}" title="{{ $pago->UsuarioRegistro }}">
                                {{ $pago->UsuarioRegistro ?? '-' }}
                            </td>
                            <td class="px-3 py-2 italic text-[11px] {{ $esTrasladado ? 'text-danger-600 dark:text-danger-400 font-bold' : 'text-gray-500 dark:text-gray-500' }}">
                                @php 
                                    $obs = $pago->Comentario ?? 'Conforme';
                                    if ($pago->solicitudResolucion && !str_contains($obs, 'Fecha')) {
                                        $fechaExc = $pago->solicitudResolucion->excedente?->Fecha;
                                        if ($fechaExc) {
                                            $obs .= "\nFecha excedente: " . \Carbon\Carbon::parse($fechaExc)->format('d/m/Y');
                                        }
                                    }
                                @endphp
                                @if($pago->EsPagoAMayor)
                                    <span class="bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400 px-1.5 py-0.5 rounded border border-warning-200 dark:border-warning-800 font-bold mr-1 inline-block mb-1 not-italic text-[10px]">PAGO A MAYOR</span>
                                @endif
                                @if($pago->EsPagoAMayorPorMora)
                                    <span class="bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400 px-1.5 py-0.5 rounded border border-danger-200 dark:border-danger-800 font-bold mr-1 inline-block mb-1 not-italic text-[10px]">A MAYOR POR MORA</span>
                                @endif
                                @if(!$pago->EsPagoAMayor && !$pago->EsPagoAMayorPorMora && !$pago->EsMora && $pago->EsPagoAutomatico)
                                    <span class="bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 font-bold mr-1 inline-block mb-1 not-italic text-[10px]">AUTOMÁTICO</span>
                                @endif
                                {!! nl2br(e($obs)) !!}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
