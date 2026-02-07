<x-filament-panels::page>
    <form wire:submit="apruebaAction" class="space-y-6">
        {{ $this->form }}

        <div class="flex gap-3">
            <x-filament::button type="submit" color="success" icon="heroicon-o-check">
                Confirmar decisión
            </x-filament::button>
            <x-filament::button wire:click="cancelar" color="gray" icon="heroicon-o-x-mark">
                Cancelar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
