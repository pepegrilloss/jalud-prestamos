@php
    $clienteId = $record->proposicion?->ClienteID ?? null;
    $clienteNombre = $record->proposicion?->cliente?->NombresApellidos ?? 'Cliente';
@endphp

@if($clienteId)
    <div class="mb-4">
        <button type="button"
            x-on:click="close(); $wire.mountTableAction('verCreditos', '{{ $clienteId }}')"
            class="inline-flex items-center gap-x-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400 transition">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Regresar a créditos de {{ $clienteNombre }}
        </button>
    </div>
@endif
