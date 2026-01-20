<div class="overflow-hidden rounded-lg bg-gradient-to-br from-blue-50 to-indigo-50 shadow-lg dark:from-gray-800 dark:to-gray-900 border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <x-heroicon-o-calendar class="w-5 h-5" />
            Apertura y Cierre del Día
        </h2>
    </div>

    <!-- Contenido -->
    <div class="p-6">
        <!-- Estado Actual -->
        <div class="mb-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium mb-2">Estado Actual</p>
            @if($this->getHoy())
                <div class="flex items-center gap-3 p-4 rounded-lg bg-white dark:bg-gray-700">
                    @if($this->getHoy()->EstadoDia === 'ABIERTO')
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-10 w-10 rounded-full bg-green-100 dark:bg-green-900">
                                <x-heroicon-s-check-circle class="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-green-700 dark:text-green-400">Día Abierto</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Abierto a las {{ $this->getHoy()->FechaApertura?->format('H:i:s') ?? 'N/A' }}
                            </p>
                        </div>
                    @else
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-10 w-10 rounded-full bg-red-100 dark:bg-red-900">
                                <x-heroicon-s-x-circle class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-red-700 dark:text-red-400">Día Cerrado</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Cerrado a las {{ $this->getHoy()->FechaCierre?->format('H:i:s') ?? 'N/A' }}
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex items-center gap-3 p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-yellow-100 dark:bg-yellow-900">
                            <x-heroicon-o-clock class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-700 dark:text-gray-300">Sin Registro</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Apertura el día de hoy</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Información del Día -->
        @if($this->getHoy())
            <div class="mb-6 p-4 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold mb-3 uppercase">Detalles</p>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Fecha</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $this->getHoy()->Fecha?->format('d/m/Y') ?? 'N/A' }}</p>
                    </div>
                    @if($this->getHoy()->FechaApertura)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Abierto por</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $this->getHoy()->usuarioApertura?->name ?? 'Sistema' }}</p>
                        </div>
                    @endif
                    @if($this->getHoy()->FechaCierre)
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Cerrado por</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $this->getHoy()->usuarioCierre?->name ?? 'Sistema' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Botones de Acción -->
        <div class="flex gap-4 pt-4">
            @if(!$this->getHoy() || $this->getHoy()->EstadoDia === 'CERRADO')
                <button
                    wire:click="aperturar"
                    class="flex-1 flex items-center justify-center gap-2 px-6 py-4 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-bold rounded-lg transition-all duration-150 transform hover:shadow-lg active:scale-95 shadow-md"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>Aperturar Día</span>
                </button>
            @endif

            @if($this->getHoy() && $this->getHoy()->EstadoDia === 'ABIERTO')
                <button
                    wire:click="cerrar"
                    class="flex-1 flex items-center justify-center gap-2 px-6 py-4 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-bold rounded-lg transition-all duration-150 transform hover:shadow-lg active:scale-95 shadow-md"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>Cerrar Día</span>
                </button>
            @endif
        </div>
    </div>
</div>
