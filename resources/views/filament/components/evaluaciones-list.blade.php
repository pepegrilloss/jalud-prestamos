<div class="space-y-4">
    @forelse($evaluaciones as $evaluacion)
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-m-calendar"
                        class="w-5 h-5 text-primary-500"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $evaluacion->FechaRegistro->format('d/m/Y H:i') }}
                    </span>
                </div>
                <x-filament::badge color="info">
                    {{ $evaluacion->UsuarioRegistro }}
                </x-filament::badge>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ $evaluacion->Comentario }}
            </div>
        </div>
    @empty
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            No hay evaluaciones registradas
        </div>
    @endforelse
</div>