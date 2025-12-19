<div class="space-y-6">
    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Registro</span>
            <p class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $evaluacion->FechaRegistro->format('d/m/Y H:i:s') }}
            </p>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrado por</span>
            <p class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ $evaluacion->UsuarioRegistro }}
            </p>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Comentario de Evaluación</h3>
        <div class="p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="text-base text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $evaluacion->Comentario }}</p>
        </div>
    </div>

    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
        <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4" />
        <span>ID de Evaluación: {{ $evaluacion->EvaluacionCreditoID }}</span>
    </div>
</div>