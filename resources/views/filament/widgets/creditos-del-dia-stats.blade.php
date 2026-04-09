{{-- Vista personalizada para el widget de stats con date picker --}}
<x-filament-widgets::widget>
    <div class="relative">
        {{-- Input date oculto que controla la fecha --}}
        <input
            type="date"
            id="widget-date-picker"
            value="{{ $fechaSeleccionada }}"
            wire:change="$set('fechaSeleccionada', $event.target.value)"
            class="absolute opacity-0 pointer-events-none"
            style="width: 0; height: 0; position: absolute; top: 0; left: 0;"
        />

        {{-- Grid de stats --}}
        <div @class([
            'fi-wi-stats-overview-stats-ctn grid gap-6',
            'md:grid-cols-2',
        ])>
            @foreach ($stats as $stat)
                {{ $stat }}
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
