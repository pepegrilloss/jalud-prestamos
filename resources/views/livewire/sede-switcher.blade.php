<div>
    @php
        $user = auth()->user();
        $esAdmin = $user?->esAdmin() ?? false;
    @endphp

    @if($esAdmin)
        <div class="flex items-center" x-data="{ open: false }" @click.away="open = false">
            <div class="relative">
                {{-- Botón del switcher --}}
                <button @click="open = !open" type="button" class="inline-flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium
                           text-gray-700 dark:text-gray-200
                           bg-white dark:bg-gray-800
                           border border-gray-300 dark:border-gray-600
                           shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700
                           transition-all duration-150 ease-in-out
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                    {{-- Icono de edificio --}}
                    <x-heroicon-m-building-office-2 class="h-4 w-4 text-primary-500" />

                    {{-- Nombre de la sede activa --}}
                    <span class="max-w-[140px] truncate">{{ $sedeNombre }}</span>

                    {{-- Flecha --}}
                    <x-heroicon-m-chevron-down class="h-4 w-4 text-gray-400 transition-transform duration-200"
                        x-bind:class="{ 'rotate-180': open }" />
                </button>

                {{-- Dropdown --}}
                <div x-show="open" x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl
                           bg-white dark:bg-gray-800
                           border border-gray-200 dark:border-gray-700
                           shadow-lg ring-1 ring-black ring-opacity-5
                           divide-y divide-gray-100 dark:divide-gray-700" style="display: none;">
                    {{-- Opción: Todas las sedes --}}
                    <div class="p-1">
                        <button wire:click="cambiarSede(0)" @click="open = false" class="group flex w-full items-center gap-x-2 rounded-lg px-3 py-2 text-sm
                                   {{ is_null($sedeActiva) ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' }}
                                   transition-colors duration-150">
                            <x-heroicon-m-globe-alt
                                class="h-4 w-4 {{ is_null($sedeActiva) ? 'text-primary-500' : 'text-gray-400' }}" />
                            <span>Todas las Sedes</span>
                            @if(is_null($sedeActiva))
                                <x-heroicon-m-check class="ml-auto h-4 w-4 text-primary-500" />
                            @endif
                        </button>
                    </div>

                    {{-- Lista de sedes --}}
                    <div class="p-1">
                        @foreach($sedes as $sede)
                            <button wire:click="cambiarSede({{ $sede->SedeID }})" @click="open = false" class="group flex w-full items-center gap-x-2 rounded-lg px-3 py-2 text-sm
                                           {{ $sedeActiva === $sede->SedeID ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' }}
                                           transition-colors duration-150">
                                <x-heroicon-m-building-office
                                    class="h-4 w-4 {{ $sedeActiva === $sede->SedeID ? 'text-primary-500' : 'text-gray-400' }}" />
                                <span>{{ $sede->Nombre }}</span>
                                @if($sedeActiva === $sede->SedeID)
                                    <x-heroicon-m-check class="ml-auto h-4 w-4 text-primary-500" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>