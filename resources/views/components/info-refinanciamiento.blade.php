@props(['proposicion', 'saldo'])

<div class="rounded-lg border-l-4 border-blue-500 bg-blue-50 p-4 mb-6">
    <div class="flex items-start justify-between">
        <div>
            <h3 class="text-sm font-semibold text-blue-900">Refinanciamiento Activo</h3>
            <p class="mt-1 text-sm text-blue-700">
                Código: <span class="font-mono font-bold">{{ $proposicion?->CodigoCredito ?? 'N/A' }}</span>
            </p>
            <p class="text-sm text-blue-700">
                Saldo Pendiente: <span class="font-bold">S/ {{ number_format($saldo ?? 0, 2) }}</span>
            </p>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center rounded-full bg-blue-200 px-3 py-1 text-sm font-medium text-blue-800">
                Refinanciamiento
            </span>
        </div>
    </div>
</div>
