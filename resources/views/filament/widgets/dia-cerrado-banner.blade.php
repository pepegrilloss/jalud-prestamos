@if(!App\Models\AperturaCierreDia::estaAbierto())
<div class="mb-6 rounded-lg border-l-4 border-red-600 bg-red-50 p-5 shadow-md dark:bg-red-950 dark:border-red-500">
    <div class="flex items-start gap-4">
        <svg class="h-6 w-6 flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
            <h3 class="font-bold text-red-800 dark:text-red-300 text-lg">⚠️ Día de Operaciones Cerrado</h3>
            <p class="text-sm text-red-700 dark:text-red-400 mt-2 leading-relaxed">
                El día de operaciones está actualmente cerrado. Solo puede visualizar los datos registrados. 
                <strong>No puede realizar operaciones de creación, edición o eliminación</strong> hasta que se aperture 
                un nuevo día. Contacte con el administrador si es necesario.
            </p>
        </div>
    </div>
</div>
@endif
