<div class="space-y-6">
    <!-- Encabezado con Cliente y Tipo de Crédito -->
    <div class="bg-gradient-to-r from-primary-50 to-primary-100 rounded-lg p-6 border border-primary-200">
        <div class="space-y-2">
            <p class="text-sm font-semibold text-primary-600 uppercase tracking-wider">Cliente</p>
            <h3 class="text-2xl font-bold text-gray-900">{{ $getRecord()->cliente->NombresApellidos }}</h3>
            <p class="text-sm text-gray-600">{{ $getRecord()->tipoCredito->Descripcion }}</p>
        </div>
    </div>

    <!-- Montos Principales en Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Monto Total -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-1">Monto Total</p>
            <p class="text-xl font-bold text-blue-900">S/. {{ number_format($getRecord()->MontoTotal, 2) }}</p>
        </div>

        <!-- Interés Total -->
        <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1">Interés Total</p>
            <p class="text-xl font-bold text-amber-900">S/. {{ number_format($getRecord()->MontoInteres, 2) }}</p>
        </div>

        <!-- Total a Pagar -->
        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
            <p class="text-xs font-semibold text-green-600 uppercase tracking-wider mb-1">Total a Pagar</p>
            <p class="text-xl font-bold text-green-900">S/. {{ number_format($getRecord()->MontoTotal + $getRecord()->MontoInteres, 2) }}</p>
        </div>
    </div>

    <!-- Detalles de Tasas y Plazos -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Tasa de Interés</p>
            <p class="text-lg font-bold text-gray-900">{{ $getRecord()->tasa->Nombre }}</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Tasa %</p>
            <p class="text-lg font-bold text-gray-900">{{ $getRecord()->TasaInteres }}%</p>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Plazo</p>
            <p class="text-lg font-bold text-gray-900">{{ $getRecord()->Plazo }} días</p>
        </div>
    </div>

    <!-- Plan de Cuotas y Mora -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-2">N° Cuotas</p>
            <p class="text-lg font-bold text-indigo-900">{{ $getRecord()->NumeroCuotas }}</p>
        </div>

        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
            <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-2">Monto por Cuota</p>
            <p class="text-lg font-bold text-indigo-900">S/. {{ number_format($getRecord()->MontoCuota, 2) }}</p>
            <p class="text-xs text-indigo-600 mt-1">{{ $getRecord()->NumeroCuotas }} cuotas</p>
        </div>

        <div class="bg-red-50 rounded-lg p-4 border border-red-200">
            <p class="text-xs font-semibold text-red-600 uppercase tracking-wider mb-2">Mora Diaria</p>
            <p class="text-lg font-bold text-red-900">S/. {{ number_format($getRecord()->TasaMora, 2) }}</p>
            <p class="text-xs text-red-600 mt-1">Por retraso en pago</p>
        </div>
    </div>
</div>
