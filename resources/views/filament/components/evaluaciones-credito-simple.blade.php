@php
    $evaluaciones = $evaluaciones ?? collect([]);
    $comentarios_proposicion = $proposicion->aprobaciones()
        ->whereNotNull('Comentario')
        ->get() ?? collect([]);
@endphp

<div class="space-y-4 text-sm">
    @if($evaluaciones->count() > 0)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <h4 class="font-semibold text-blue-900 mb-2">📋 Evaluaciones de Crédito</h4>
            <div class="space-y-1">
                @foreach($evaluaciones as $evaluacion)
                    <p class="text-gray-700">{{ $evaluacion->Comentario }}</p>
                    <p class="text-xs text-gray-500">{{ $evaluacion->FechaRegistro?->format('d/m/Y H:i') }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if($comentarios_proposicion->count() > 0)
        <div class="rounded-lg border border-green-200 bg-green-50 p-3">
            <h4 class="font-semibold text-green-900 mb-2">✓ Aprobaciones</h4>
            <div class="space-y-1">
                @foreach($comentarios_proposicion as $aprobacion)
                    <p class="text-gray-700"><strong>{{ $aprobacion->nivel->Nombre ?? 'Nivel' }}:</strong> {{ $aprobacion->Comentario ?? 'Sin comentario' }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if($evaluaciones->isEmpty() && $comentarios_proposicion->isEmpty())
        <p class="text-gray-500 italic">Sin evaluaciones registradas</p>
    @endif
</div>
