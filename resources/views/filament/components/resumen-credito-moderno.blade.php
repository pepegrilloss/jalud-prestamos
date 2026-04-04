<div class="space-y-6">
    <!-- Encabezado con Cliente y Tipo de Crédito -->
    <div class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/10 rounded-lg p-6 border border-primary-200 dark:border-primary-800/30">
        <div class="space-y-2">
            <p class="text-sm font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider">Cliente</p>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $getRecord()->cliente->NombresApellidos }}</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $getRecord()->tipoCredito->Descripcion }}</p>
        </div>
    </div>

    <!-- Montos Principales en Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Monto Total -->
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800/30">
            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-1">Monto Total</p>
            <p class="text-xl font-bold text-blue-900 dark:text-blue-100">S/. {{ number_format($getRecord()->MontoTotal, 2) }}</p>
        </div>

        <!-- Interés Total -->
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800/30">
            <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1">Interés Total</p>
            <p class="text-xl font-bold text-amber-900 dark:text-amber-100">S/. {{ number_format($getRecord()->MontoInteres, 2) }}</p>
        </div>

        <!-- Total a Pagar -->
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800/30">
            <p class="text-xs font-semibold text-green-600 dark:text-green-400 uppercase tracking-wider mb-1">Total a Pagar</p>
            <p class="text-xl font-bold text-green-900 dark:text-green-100">S/. {{ number_format($getRecord()->MontoTotal + $getRecord()->MontoInteres, 2) }}</p>
        </div>
    </div>

    <!-- Detalles de Tasas y Plazos -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-4 border border-gray-200 dark:border-white/10">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Tasa de Interés</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $getRecord()->tasa->Nombre }}</p>
        </div>

        <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-4 border border-gray-200 dark:border-white/10">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Tasa %</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $getRecord()->TasaInteres }}%</p>
        </div>

        <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-4 border border-gray-200 dark:border-white/10">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Plazo</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $getRecord()->Plazo }} días</p>
        </div>
    </div>

    <!-- Plan de Cuotas y Mora -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800/30">
            <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-2">N° Cuotas</p>
            <p class="text-lg font-bold text-indigo-900 dark:text-indigo-100">{{ $getRecord()->NumeroCuotas }}</p>
        </div>

        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800/30">
            <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-2">Monto por Cuota</p>
            <p class="text-lg font-bold text-indigo-900 dark:text-indigo-100">S/. {{ number_format($getRecord()->MontoCuota, 2) }}</p>
            <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">{{ $getRecord()->NumeroCuotas }} cuotas</p>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800/30">
            <p class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2">Mora Diaria</p>
            <p class="text-lg font-bold text-red-900 dark:text-red-100">S/. {{ number_format($getRecord()->TasaMora, 2) }}</p>
            <p class="text-xs text-red-600 dark:text-red-400 mt-1">Por retraso en pago</p>
        </div>
    </div>
</div>
