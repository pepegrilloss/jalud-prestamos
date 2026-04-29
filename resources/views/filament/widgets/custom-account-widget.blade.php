<x-filament-widgets::widget class="fi-account-widget">
@php
    $user = filament()->auth()->user();
    $sedeNombre = 'No asignada';

    if ($user) {
        if ($user->esAdmin()) {
            $sedeActiva = session('sede_activa');
            if ($sedeActiva) {
                $sedeObj = \App\Models\Sede::withoutGlobalScopes()->find($sedeActiva);
                $sedeNombre = $sedeObj?->Nombre ?? 'Sede desconocida';
            } else {
                $sedeNombre = 'Todas las Sedes';
            }
        } elseif ($user->sede) {
            $sedeNombre = $user->sede->Nombre;
        }
    }
@endphp
<x-filament::section>
        <div class="flex items-center gap-x-3">
            <x-filament-panels::avatar.user size="lg" :user="$user" />

            <div class="flex-1">
                <h2 class="grid flex-1 text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Bienvenida/o {{ filament()->getUserName($user) }}
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon alias="panels::widgets.account.sede" icon="heroicon-m-building-office"
                        class="h-4 w-4 inline-block mr-1" />
                    {{ $sedeNombre }}
                </p>
            </div>

            <form action="{{ filament()->getLogoutUrl() }}" method="post" class="my-auto">
                @csrf

                <x-filament::button color="gray" icon="heroicon-m-arrow-left-on-rectangle"
                    icon-alias="panels::widgets.account.logout-button" labeled-from="sm" tag="button" type="submit">
                    {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>