<x-filament-panels::page>
    <x-filament-panels::form wire:submit="create">
        {{ $this->form }}
        
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{-- Modal de Confirmación --}}
    <x-filament::modal 
        id="confirm-payment" 
        width="md"
        :close-by-clicking-away="false"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-warning-500" />
                <span>Confirmar Registro de Pago</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <p class="text-base font-semibold text-gray-900 dark:text-white">
                ¿ESTÁ SEGURO DE REGISTRAR ESTE PAGO?
            </p>
            
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 space-y-3">
                @php
                    $clienteData = $this->getClienteData();
                @endphp
                
                <div class="flex items-start gap-2">
                    <span class="text-2xl">👤</span>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cliente</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $clienteData['cliente'] }}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-2">
                    <span class="text-2xl">🆔</span>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">DNI</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $clienteData['dni'] }}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-2">
                    <span class="text-2xl">💰</span>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Monto</p>
                        <p class="font-semibold text-green-600 dark:text-green-400 text-lg">S/ {{ $clienteData['monto'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                color="gray"
                x-on:click="close"
            >
                ✗ No, Cancelar
            </x-filament::button>

            <x-filament::button
                color="success"
                wire:click="confirmAndCreate"
                x-on:click="close"
            >
                ✓ Sí, Registrar Pago
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>