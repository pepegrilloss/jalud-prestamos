<x-filament-panels::page>
    <form wire:submit.prevent="create" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-start gap-3">
            <x-filament::button type="submit" size="lg">
                Crear Solicitud
            </x-filament::button>
            <x-filament::button tag="a" href="{{ \App\Filament\Resources\SolicitudExoneracionResource::getUrl('index') }}" color="gray" size="lg">
                Cancelar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
