@php
    $record = $getRecord();
    
    // Si no hay record en el contexto, intentar obtener de la página (Create)
    if (!$record && isset($livewire)) {
        $proposicionId = request()->query('proposicion');
        if ($proposicionId) {
            $record = \App\Models\ProposicionCredito::find($proposicionId);
        }
    }
    
    if (!$record) {
        return;
    }
    
    $evaluaciones = $record->cliente->evaluacionesCredito ?? collect([]);
    $comentarios_proposicion = $record->aprobaciones()
        ->whereNotNull('Comentario')
        ->get();
@endphp

<div class="space-y-4">
    {{-- Comentarios de Evaluación de Crédito --}}
    @if($evaluaciones->count() > 0)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h3 class="font-bold text-blue-900 mb-2">📋 Comentarios de Evaluación de Crédito</h3>
            <div class="space-y-2">
                @foreach($evaluaciones as $evaluacion)
                    <div class="text-sm bg-white p-2 rounded border-l-2 border-blue-400">
                        <p class="text-gray-900">{{ $evaluacion->Comentario }}</p>
                        <p class="text-xs text-gray-600 mt-1">{{ $evaluacion->FechaRegistro?->format('d/m/Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Comentarios de Aprobación de Proposición --}}
    @if($comentarios_proposicion->count() > 0)
        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
            <h3 class="font-bold text-green-900 mb-2">✓ Comentarios de Aprobación</h3>
            <div class="space-y-2">
                @foreach($comentarios_proposicion as $aprobacion)
                    <div class="text-sm bg-white p-2 rounded border-l-2 border-green-400">
                        <p class="font-semibold text-gray-900">{{ $aprobacion->nivel->Nombre }}</p>
                        <p class="text-gray-900 mt-1">{{ $aprobacion->Comentario ?? 'Sin comentario' }}</p>
                        @if($aprobacion->Estado === 'APROBADO' && $aprobacion->aprobador)
                            <p class="text-xs text-gray-600 mt-1">Aprobado por {{ $aprobacion->aprobador->name }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($evaluaciones->count() === 0 && $comentarios_proposicion->count() === 0)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 text-center">
            Sin comentarios registrados
        </div>
    @endif
</div>
