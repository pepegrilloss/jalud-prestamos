@php
    $aprobaciones = $getRecord()->aprobaciones()
        ->with('nivel')
        ->orderBy('NivelAprobacionID', 'asc')
        ->get();
@endphp

<div class="flex flex-col gap-3 py-2">
    @foreach($aprobaciones as $aprobacion)
        <div class="flex items-center gap-3 p-3 rounded-lg border-l-4 @if($aprobacion->Estado === 'APROBADO') bg-green-50 border-green-500 @elseif($aprobacion->Estado === 'RECHAZADO') bg-red-50 border-red-500 @else bg-yellow-50 border-yellow-400 @endif">
            {{-- Icono de estado --}}
            <div class="w-6 h-6 flex-shrink-0">
                @if($aprobacion->Estado === 'APROBADO')
                    <svg class="w-full h-full text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                @elseif($aprobacion->Estado === 'RECHAZADO')
                    <svg class="w-full h-full text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                @else
                    <svg class="w-full h-full text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 102 0V6z" clip-rule="evenodd" />
                    </svg>
                @endif
            </div>

            {{-- Nombre del nivel y estado --}}
            <div class="flex-1">
                <div class="text-sm font-semibold @if($aprobacion->Estado === 'APROBADO') text-green-900 @elseif($aprobacion->Estado === 'RECHAZADO') text-red-900 @else text-yellow-900 @endif">
                    {{ $aprobacion->nivel->Nombre }}
                </div>
                <div class="text-xs mt-1 @if($aprobacion->Estado === 'APROBADO') text-green-700 @elseif($aprobacion->Estado === 'RECHAZADO') text-red-700 @else text-yellow-700 @endif">
                    @if($aprobacion->Estado === 'APROBADO')
                        <span class="font-bold">✓ Aprobado</span>
                        @if($aprobacion->aprobador)
                            por <strong>{{ $aprobacion->aprobador->name }}</strong>
                        @endif
                    @elseif($aprobacion->Estado === 'RECHAZADO')
                        <span class="font-bold">✗ Rechazado</span>
                        @if($aprobacion->aprobador)
                            por <strong>{{ $aprobacion->aprobador->name }}</strong>
                        @endif
                    @else
                        <span class="font-bold">◉ Pendiente de Aprobación</span>
                    @endif
                </div>
            </div>

            {{-- Indicador visual de orden --}}
            <div class="flex-shrink-0">
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white rounded-full @if($aprobacion->Estado === 'APROBADO') bg-green-600 @elseif($aprobacion->Estado === 'RECHAZADO') bg-red-600 @else bg-yellow-500 @endif">
                    {{ $aprobacion->nivel->Orden }}
                </span>
            </div>
        </div>
    @endforeach
</div>
