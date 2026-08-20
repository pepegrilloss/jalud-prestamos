<div class="space-y-4">
    {{-- Información del cliente --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Cliente</h3>
        <p class="text-base font-medium text-gray-900 dark:text-gray-100">
            {{ $traspaso->cliente?->NombresApellidos ?? 'N/A' }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            DNI: {{ $traspaso->cliente?->DNI ?? 'N/A' }}
        </p>
    </div>

    {{-- Cambio de zona --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
            <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18" />
                </svg>
                Antes
            </h3>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                <strong>Zona:</strong> {{ $traspaso->zonaAnterior?->Nombre ?? 'N/A' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <strong>Promotor:</strong> {{ $traspaso->promotorAnterior?->Descripcion ?? '—' }}
            </p>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <h3 class="text-sm font-semibold text-green-600 dark:text-green-400 uppercase tracking-wider mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline mr-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
                Después
            </h3>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                <strong>Zona:</strong> {{ $traspaso->zonaNueva?->Nombre ?? 'N/A' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <strong>Promotor:</strong> {{ $traspaso->promotorNuevo?->Descripcion ?? '—' }}
            </p>
        </div>
    </div>

    {{-- Motivo --}}
    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
        <h3 class="text-sm font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-2">Motivo del Traspaso</h3>
        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $traspaso->MotivoTraspaso }}</p>
    </div>

    {{-- Metadatos --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 grid grid-cols-2 gap-3">
        <div>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Ejecutado por</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $traspaso->userSolicita?->name ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Fecha y Hora</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $traspaso->FechaTraspaso->format('d/m/Y H:i:s') }}</p>
        </div>
        <div>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Sede</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $traspaso->sede?->Nombre ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">ID Registro</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">#{{ $traspaso->id }}</p>
        </div>
    </div>
</div>
