<x-filament-panels::page>
    {{-- Saldo actual de Caja Gerencia --}}
    <div class="mb-4">
        <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-3">
                <div class="flex-1">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Saldo Actual — Caja Abierta Gerencia
                    </span>
                    <p class="mt-1 text-2xl font-bold tracking-tight {{ $this->getSaldoActual() > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                        S/ {{ number_format($this->getSaldoActual(), 2) }}
                    </p>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        Saldo en vivo desde Fondos de Sedes
                    </span>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <x-heroicon-o-building-library class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
