<div class="px-2 py-4">
    <!-- Cliente Section -->
    <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-5 mb-5 border border-gray-200 dark:border-gray-700 w-full text-center shadow-sm">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 mb-3">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Cliente</p>
        <h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white uppercase tracking-tight">{{ $nombre }}</h3>
    </div>

    <!-- Detalles Section -->
    <div class="grid grid-cols-1 gap-3 w-full">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg text-blue-600 dark:text-blue-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tipo de Crédito</p>
                    <p class="text-base md:text-lg font-bold text-gray-900 dark:text-white">{{ $tipoCredito }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg text-purple-600 dark:text-purple-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Método de Pago</p>
                    <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-bold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                        {{ $metodoPago }}
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-emerald-50 dark:bg-gray-800 rounded-xl p-4 md:p-5 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3 border-l-4 border-emerald-500 dark:border-emerald-400 pl-3 md:pl-4">
                <div>
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Monto a Pagar</p>
                    <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400 tracking-tight">S/ {{ $monto }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
