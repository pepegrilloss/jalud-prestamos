<x-filament-panels::page>

    <x-filament::section>
        {{ $this->table }}
    </x-filament::section>

    <x-filament::section class="mt-6">
        {{ $this->getRegisteredTable() }}
    </x-filament::section>

</x-filament-panels::page>
