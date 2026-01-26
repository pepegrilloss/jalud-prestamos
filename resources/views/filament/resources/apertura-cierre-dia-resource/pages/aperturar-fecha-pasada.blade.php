<x-filament-panels::page>
    <form wire:submit="aperturar" class="space-y-6">
        {{ $this->form }}

        <div class="flex gap-3 justify-end">
            <x-filament::button
                color="gray"
                tag="a"
                href="{{ $this->getResource()::getUrl('index') }}"
            >
                Cancelar
            </x-filament::button>

            <x-filament::button type="submit" color="info">
                Aperturar Fecha
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
